<?php

/**
 * @file plugins/paymethod/paystack/PaystackPaymentPlugin.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackPaymentPlugin
 *
 * @brief Paystack hosted-checkout payment plugin for OJS 3.4.
 */

namespace APP\plugins\paymethod\paystack;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\paymethod\paystack\classes\PaystackClient;
use APP\plugins\paymethod\paystack\classes\PaystackSchemaMigration;
use APP\plugins\paymethod\paystack\mailables\PaymentConfirmation;
use APP\plugins\paymethod\paystack\mailables\PaymentConfirmationAdmin;
use APP\plugins\paymethod\paystack\mailables\PaymentFailed;
use APP\plugins\paymethod\paystack\mailables\PaymentRefunded;
use APP\template\TemplateManager;
use Exception;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\payment\QueuedPayment;
use PKP\plugins\Hook;
use PKP\plugins\PaymethodPlugin;
use Slim\Http\Request as SlimRequest;

class PaystackPaymentPlugin extends PaymethodPlugin
{
    public const SUPPORTED_CURRENCIES = ['NGN', 'USD', 'GHS', 'ZAR', 'KES', 'XOF'];
    public const SECRET_MASK = '********';

    /**
     * @copydoc Plugin::getName()
     */
    public function getName()
    {
        return 'PaystackPayment';
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.paymethod.paystack.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.paymethod.paystack.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            $this->addLocaleData();
            Hook::add('Form::config::before', [$this, 'addSettings']);
            Hook::add('Mailer::Mailables', [$this, 'addMailable']);
            Hook::add('TemplateManager::display', [$this, 'loadFrontendStyles']);
            if ($this->getEnabled($mainContextId)) {
                $this->ensureSchema();
            }
        }
        return $success;
    }

    /**
     * @copydoc Plugin::getInstallMigration()
     */
    public function getInstallMigration(): ?Migration
    {
        return new PaystackSchemaMigration();
    }

    /**
     * @copydoc Plugin::getInstallEmailTemplatesFile()
     */
    public function getInstallEmailTemplatesFile()
    {
        return $this->getPluginPath() . '/emailTemplates.xml';
    }

    /**
     * Inject Paystack settings into Distribution > Payments.
     *
     * @param string $hookName
     * @param \PKP\components\forms\FormComponent $form
     */
    public function addSettings($hookName, $form)
    {
        import('lib.pkp.classes.components.forms.context.PKPPaymentSettingsForm'); // FORM_PAYMENT_SETTINGS
        if ($form->id !== FORM_PAYMENT_SETTINGS) {
            return;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return;
        }

        $contextId = (int) $context->getId();
        $callbackUrl = $request->url(null, 'payment', 'plugin', [$this->getName(), 'callback']);
        $webhookUrl = $request->url(null, 'payment', 'plugin', [$this->getName(), 'webhook']);
        $currencies = implode(', ', self::SUPPORTED_CURRENCIES);

        $form->addGroup([
            'id' => 'paystackpayment',
            'label' => __('plugins.paymethod.paystack.settings'),
            'showWhen' => 'paymentsEnabled',
        ])
            ->addField(new \PKP\components\forms\FieldHTML('paystackUrls', [
                'label' => __('plugins.paymethod.paystack.settings.urls'),
                'description' => '<p>' . htmlspecialchars(__('plugins.paymethod.paystack.settings.urls.description'), ENT_QUOTES, 'UTF-8') . '</p>'
                    . '<p><strong>' . htmlspecialchars(__('plugins.paymethod.paystack.settings.callbackUrl'), ENT_QUOTES, 'UTF-8') . ':</strong><br><code>' . htmlspecialchars($callbackUrl, ENT_QUOTES, 'UTF-8') . '</code></p>'
                    . '<p><strong>' . htmlspecialchars(__('plugins.paymethod.paystack.settings.webhookUrl'), ENT_QUOTES, 'UTF-8') . ':</strong><br><code>' . htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8') . '</code></p>'
                    . '<p>' . htmlspecialchars(__('plugins.paymethod.paystack.settings.supportedCurrencies'), ENT_QUOTES, 'UTF-8') . ': ' . htmlspecialchars($currencies, ENT_QUOTES, 'UTF-8') . '</p>',
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldOptions('testMode', [
                'label' => __('plugins.paymethod.paystack.settings.testMode'),
                'description' => __('plugins.paymethod.paystack.settings.testMode.description'),
                'options' => [
                    ['value' => true, 'label' => __('common.enable')],
                ],
                'value' => $this->isTestMode($contextId),
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('testPublicKey', [
                'label' => __('plugins.paymethod.paystack.settings.testPublicKey'),
                'value' => (string) $this->getSetting($contextId, 'testPublicKey'),
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('testSecretKey', [
                'label' => __('plugins.paymethod.paystack.settings.testSecretKey'),
                'description' => __('plugins.paymethod.paystack.settings.secretKeyMasked'),
                'value' => $this->maskedOrEmpty((string) $this->getSetting($contextId, 'testSecretKey')),
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('livePublicKey', [
                'label' => __('plugins.paymethod.paystack.settings.livePublicKey'),
                'value' => (string) $this->getSetting($contextId, 'livePublicKey'),
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldText('liveSecretKey', [
                'label' => __('plugins.paymethod.paystack.settings.liveSecretKey'),
                'description' => __('plugins.paymethod.paystack.settings.secretKeyMasked'),
                'value' => $this->maskedOrEmpty((string) $this->getSetting($contextId, 'liveSecretKey')),
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldOptions('sendConfirmationEmail', [
                'label' => __('plugins.paymethod.paystack.settings.sendConfirmationEmail'),
                'options' => [
                    ['value' => true, 'label' => __('common.enable')],
                ],
                'value' => $this->settingFlag($contextId, 'sendConfirmationEmail', true),
                'groupId' => 'paystackpayment',
            ]))
            ->addField(new \PKP\components\forms\FieldOptions('sendAdminNotification', [
                'label' => __('plugins.paymethod.paystack.settings.sendAdminNotification'),
                'options' => [
                    ['value' => true, 'label' => __('common.enable')],
                ],
                'value' => $this->settingFlag($contextId, 'sendAdminNotification', true),
                'groupId' => 'paystackpayment',
            ]));
    }

    /**
     * @copydoc PaymethodPlugin::saveSettings()
     */
    public function saveSettings(string $hookname, array $args)
    {
        $slimRequest = $args[0]; /** @var SlimRequest $slimRequest */
        $request = $args[1]; /** @var Request $request */
        $updatedSettings = $args[3]; /** @var Collection $updatedSettings */

        $context = $request->getContext();
        if (!$context) {
            return;
        }
        $contextId = (int) $context->getId();
        $allParams = $slimRequest->getParsedBody();
        if (!is_array($allParams)) {
            $allParams = [];
        }

        $bools = ['testMode', 'sendConfirmationEmail', 'sendAdminNotification'];
        foreach ($bools as $name) {
            $value = isset($allParams[$name]) && $allParams[$name] === 'true';
            $this->updateSetting($contextId, $name, $value);
            $updatedSettings->put($name, $value);
        }

        foreach (['testPublicKey', 'livePublicKey'] as $name) {
            if (!array_key_exists($name, $allParams)) {
                continue;
            }
            $value = trim((string) $allParams[$name]);
            $this->updateSetting($contextId, $name, $value);
            $updatedSettings->put($name, $value);
        }

        foreach (['testSecretKey', 'liveSecretKey'] as $name) {
            if (!array_key_exists($name, $allParams)) {
                continue;
            }
            $value = trim((string) $allParams[$name]);
            if ($this->isMaskedSecret($value)) {
                continue;
            }
            $this->updateSetting($contextId, $name, $value);
            $updatedSettings->put($name, self::SECRET_MASK);
        }
    }

    /**
     * @copydoc PaymethodPlugin::isConfigured()
     */
    public function isConfigured($context)
    {
        if (!$context) {
            return false;
        }
        $contextId = (int) $context->getId();
        return $this->getPublicKey($contextId) !== '' && $this->getSecretKey($contextId) !== '';
    }

    /**
     * @copydoc PaymethodPlugin::getPaymentForm()
     */
    public function getPaymentForm($context, $queuedPayment)
    {
        return new PaystackPaymentForm($this, $queuedPayment);
    }

    /**
     * @copydoc PaymethodPlugin::handle()
     */
    public function handle($args, $request)
    {
        $op = isset($args[0]) ? (string) $args[0] : '';
        switch ($op) {
            case 'initiate':
                $this->handleInitiate($request);
                return;
            case 'callback':
                $this->handleCallback($request);
                return;
            case 'webhook':
                $this->handleWebhook($request);
                return;
            case 'history':
                $this->handleHistory($request);
                return;
            case 'receipt':
                $this->handleReceipt($request);
                return;
            default:
                $request->redirect(null, 'index');
        }
    }

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }
        $router = $request->getRouter();
        $actions[] = new LinkAction(
            'transactions',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, [
                    'verb' => 'transactions',
                    'plugin' => $this->getName(),
                    'category' => 'paymethod',
                ]),
                __('plugins.paymethod.paystack.transactions.tab')
            ),
            __('plugins.paymethod.paystack.transactions.tab')
        );
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request)
    {
        switch ($request->getUserVar('verb')) {
            case 'transactions':
                return $this->manageTransactions($request);
            case 'transactionDetails':
                return $this->manageTransactionDetails($request);
            case 'refund':
                return $this->manageRefund($request);
        }
        return parent::manage($args, $request);
    }

    /**
     * Register plugin mailables.
     */
    public function addMailable(string $hookName, array $args): void
    {
        $args[0]->push(PaymentConfirmation::class);
        $args[0]->push(PaymentConfirmationAdmin::class);
        $args[0]->push(PaymentFailed::class);
        $args[0]->push(PaymentRefunded::class);
    }

    /**
     * Attach frontend CSS on Paystack pages.
     */
    public function loadFrontendStyles(string $hookName, array $args): bool
    {
        $templateMgr = $args[0];
        $template = (string) ($args[1] ?? '');
        if (
            strpos($template, 'paymentDetails.tpl') === false
            && strpos($template, 'paymentConfirmation.tpl') === false
            && strpos($template, 'paymentHistory.tpl') === false
            && strpos($template, 'paymentReceipt.tpl') === false
        ) {
            return false;
        }
        $request = Application::get()->getRequest();
        $templateMgr->addStyleSheet(
            'paystackFrontendCss',
            $request->getBaseUrl() . '/' . $this->getPluginPath() . '/css/frontend.css',
            ['contexts' => 'frontend']
        );
        return false;
    }

    public function isTestMode(int $contextId): bool
    {
        return (bool) $this->getSetting($contextId, 'testMode');
    }

    public function getPublicKey(int $contextId): string
    {
        $key = $this->isTestMode($contextId)
            ? (string) $this->getSetting($contextId, 'testPublicKey')
            : (string) $this->getSetting($contextId, 'livePublicKey');
        return trim($key);
    }

    public function getSecretKey(int $contextId): string
    {
        $key = $this->isTestMode($contextId)
            ? (string) $this->getSetting($contextId, 'testSecretKey')
            : (string) $this->getSetting($contextId, 'liveSecretKey');
        return trim($key);
    }

    public function isHttpsRequest(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwarded === 'https';
    }

    public static function currencySymbol(string $code): string
    {
        $map = [
            'NGN' => '₦',
            'USD' => '$',
            'GHS' => 'GH₵',
            'ZAR' => 'R',
            'KES' => 'KSh ',
            'XOF' => 'CFA ',
        ];
        $code = strtoupper($code);
        return $map[$code] ?? ($code . ' ');
    }

    private function handleInitiate(Request $request): void
    {
        if (Config::getVar('general', 'sandbox', false)) {
            $this->showMessage($request, 'common.sandbox');
            return;
        }
        if (!$request->isPost() || !$request->checkCSRF()) {
            $this->showMessage($request, 'form.csrfInvalid');
            return;
        }

        $journal = $request->getJournal();
        $user = $request->getUser();
        $queuedPayment = $this->getQueuedPayment((int) $request->getUserVar('queuedPaymentId'));
        if (!$journal || !$user || !$queuedPayment) {
            $this->showMessage($request, 'plugins.paymethod.paystack.error');
            return;
        }
        $contextId = (int) $journal->getId();
        if ((int) $queuedPayment->getUserId() !== (int) $user->getId()) {
            $this->showMessage($request, 'user.authorization.accessDenied');
            return;
        }
        if (!$this->isHttpsRequest() && !$this->isTestMode($contextId)) {
            $this->showMessage($request, 'plugins.paymethod.paystack.error.httpsRequired');
            return;
        }
        if (!$this->isConfigured($journal)) {
            $this->showMessage($request, 'plugins.paymethod.paystack.error.configuration');
            return;
        }

        $currency = strtoupper((string) $queuedPayment->getCurrencyCode());
        if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            $this->showMessage($request, 'plugins.paymethod.paystack.error.currency');
            return;
        }

        $paymentManager = Application::getPaymentManager($journal);
        $paymentName = method_exists($paymentManager, 'getPaymentName')
            ? $paymentManager->getPaymentName($queuedPayment)
            : __('common.payment');

        $reference = 'OJS' . $queuedPayment->getId() . '_' . time() . '_' . random_int(10000, 99999);
        $callbackUrl = $request->url(null, 'payment', 'plugin', [$this->getName(), 'callback'], [
            'queuedPaymentId' => $queuedPayment->getId(),
            'reference' => $reference,
        ]);

        try {
            $this->ensureSchema();
            $result = $this->client($contextId)->initializeTransaction([
                'email' => $user->getEmail(),
                'amount' => PaystackClient::toSubunit((float) $queuedPayment->getAmount()),
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'queuedPaymentId' => $queuedPayment->getId(),
                    'contextId' => $contextId,
                    'userId' => $user->getId(),
                    'paymentName' => $paymentName,
                    'cancel_action' => 'https://cancel.paystack.com',
                ],
            ]);
            $authUrl = $result['data']['authorization_url'] ?? '';
            if ($authUrl === '') {
                throw new Exception('Paystack did not return a checkout URL.');
            }
            $this->upsertPaymentRecord($contextId, [
                'queued_payment_id' => (int) $queuedPayment->getId(),
                'user_id' => (int) $user->getId(),
                'reference' => $reference,
                'status' => 'pending',
                'amount' => (float) $queuedPayment->getAmount(),
                'currency' => $currency,
            ]);
            $request->redirectUrl($authUrl);
        } catch (Exception $e) {
            error_log('Paystack initiate failed: ' . $e->getMessage());
            $this->showMessage($request, 'plugins.paymethod.paystack.error');
        }
    }

    private function handleCallback(Request $request): void
    {
        $journal = $request->getJournal();
        $reference = trim((string) $request->getUserVar('reference'));
        $queuedPayment = $this->getQueuedPayment((int) $request->getUserVar('queuedPaymentId'));
        if (!$journal || $reference === '' || !$queuedPayment) {
            $this->showMessage($request, 'plugins.paymethod.paystack.error.verificationFailed');
            return;
        }
        $contextId = (int) $journal->getId();
        $user = $request->getUser();
        if ($user && (int) $queuedPayment->getUserId() !== (int) $user->getId()) {
            $this->showMessage($request, 'user.authorization.accessDenied');
            return;
        }

        try {
            $verified = $this->client($contextId)->verifyTransaction($reference);
            $data = $verified['data'] ?? [];
            $this->assertVerifiedMatchesQueued($queuedPayment, $data, $reference);
            $this->fulfillIfNeeded($request, $queuedPayment, $reference, $data);
            $this->showConfirmation($request, $queuedPayment, $reference, $data);
        } catch (Exception $e) {
            error_log('Paystack callback failed: ' . $e->getMessage());
            $this->notifyFailed($journal, $queuedPayment, $reference, $e->getMessage());
            $this->showMessage($request, 'plugins.paymethod.paystack.error.verificationFailed');
        }
    }

    private function handleWebhook(Request $request): void
    {
        $journal = $request->getJournal();
        if (!$journal) {
            $this->webhookResponse(400, 'Missing journal context');
            return;
        }
        $contextId = (int) $journal->getId();
        $rawBody = (string) file_get_contents('php://input');
        $signature = $this->paystackSignatureHeader();
        $secret = $this->getSecretKey($contextId);
        $expected = hash_hmac('sha512', $rawBody, $secret);
        if ($secret === '' || $signature === '' || !hash_equals($expected, $signature)) {
            $this->webhookResponse(401, 'Invalid signature');
            return;
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            $this->webhookResponse(400, 'Invalid JSON');
            return;
        }
        $event = (string) ($payload['event'] ?? '');
        $data = $payload['data'] ?? [];
        $reference = (string) ($data['reference'] ?? '');
        if ($event === '' || $reference === '') {
            $this->webhookResponse(200, 'Ignored');
            return;
        }

        $this->ensureSchema();
        if (!$this->claimWebhookEvent($contextId, $event, $reference)) {
            $this->webhookResponse(200, 'Duplicate');
            return;
        }

        if ($event !== 'charge.success') {
            $this->webhookResponse(200, 'Unhandled event');
            return;
        }

        try {
            $verified = $this->client($contextId)->verifyTransaction($reference);
            $verifyData = $verified['data'] ?? [];
            $queuedPaymentId = (int) ($verifyData['metadata']['queuedPaymentId'] ?? $data['metadata']['queuedPaymentId'] ?? 0);
            if ($queuedPaymentId < 1) {
                $record = $this->getPaymentRecordByReference($contextId, $reference);
                $queuedPaymentId = (int) ($record['queued_payment_id'] ?? 0);
            }
            $queuedPayment = $this->getQueuedPayment($queuedPaymentId);
            if (!$queuedPayment) {
                $this->webhookResponse(200, 'Already fulfilled or missing');
                return;
            }
            $this->assertVerifiedMatchesQueued($queuedPayment, $verifyData, $reference);
            $this->fulfillIfNeeded($request, $queuedPayment, $reference, $verifyData);
            $this->webhookResponse(200, 'Fulfilled');
        } catch (Exception $e) {
            error_log('Paystack webhook failed: ' . $e->getMessage());
            $this->webhookResponse(500, 'Processing error');
        }
    }

    private function handleHistory(Request $request): void
    {
        $journal = $request->getJournal();
        $user = $request->getUser();
        if (!$journal || !$user) {
            $this->showMessage($request, 'user.login.loginRequired');
            return;
        }
        $this->ensureSchema();
        $rows = DB::table('paystack_payments')
            ->where('context_id', (int) $journal->getId())
            ->where('user_id', (int) $user->getId())
            ->whereIn('status', ['success', 'refunded', 'partial_refund'])
            ->orderBy('created_at', 'desc')
            ->get();

        $payments = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $payments[] = [
                'reference' => $row['reference'],
                'amount' => (float) $row['amount'],
                'currency' => $row['currency'],
                'status' => $row['status'],
                'createdAt' => $row['created_at'],
                'receiptUrl' => $request->url(null, 'payment', 'plugin', [$this->getName(), 'receipt'], [
                    'reference' => $row['reference'],
                ]),
            ];
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => 'plugins.paymethod.paystack.paymentHistory.title',
            'payments' => $payments,
        ]);
        $templateMgr->display($this->getTemplateResource('paymentHistory.tpl'));
    }

    private function handleReceipt(Request $request): void
    {
        $journal = $request->getJournal();
        $user = $request->getUser();
        $reference = trim((string) $request->getUserVar('reference'));
        if (!$journal || !$user || $reference === '') {
            $this->showMessage($request, 'user.authorization.accessDenied');
            return;
        }
        $record = $this->getPaymentRecordByReference((int) $journal->getId(), $reference);
        if (!$record || (int) $record['user_id'] !== (int) $user->getId()) {
            $this->showMessage($request, 'user.authorization.accessDenied');
            return;
        }
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => 'plugins.paymethod.paystack.paymentReceipt.title',
            'record' => $record,
            'currencySymbol' => self::currencySymbol((string) $record['currency']),
            'payerName' => $user->getFullName(),
            'payerEmail' => $user->getEmail(),
            'journalName' => $journal->getLocalizedName(),
        ]);
        $templateMgr->display($this->getTemplateResource('paymentReceipt.tpl'));
    }

    /**
     * @param array $data Paystack verify payload data
     */
    private function assertVerifiedMatchesQueued(QueuedPayment $queuedPayment, array $data, string $reference): void
    {
        if (($data['status'] ?? '') !== 'success') {
            throw new Exception('Paystack transaction is not successful.');
        }
        $paidReference = (string) ($data['reference'] ?? '');
        if ($paidReference !== $reference) {
            throw new Exception('Paystack reference mismatch.');
        }
        $metaQueueId = (int) ($data['metadata']['queuedPaymentId'] ?? 0);
        if ($metaQueueId && $metaQueueId !== (int) $queuedPayment->getId()) {
            throw new Exception('Queued payment id mismatch.');
        }
        $paidAmount = PaystackClient::fromSubunit($data['amount'] ?? 0);
        $paidCurrency = strtoupper((string) ($data['currency'] ?? ''));
        if (abs($paidAmount - (float) $queuedPayment->getAmount()) >= 0.009) {
            throw new Exception('Paid amount does not match the queued payment.');
        }
        if ($paidCurrency !== strtoupper((string) $queuedPayment->getCurrencyCode())) {
            throw new Exception('Paid currency does not match the queued payment.');
        }
    }

    private function fulfillIfNeeded(Request $request, QueuedPayment $queuedPayment, string $reference, array $data): void
    {
        $context = $request->getContext();
        $contextId = (int) $context->getId();
        if (!$this->claimFulfillment($contextId, (int) $queuedPayment->getId(), $reference)) {
            return;
        }

        $paymentManager = Application::getPaymentManager($context);
        $paymentManager->fulfillQueuedPayment($request, $queuedPayment, $this->getName());

        $completedId = null;
        $completedPaymentDao = DAORegistry::getDAO('OJSCompletedPaymentDAO');
        if ($completedPaymentDao && method_exists($completedPaymentDao, 'getByAssoc')) {
            $completed = $completedPaymentDao->getByAssoc(
                $queuedPayment->getUserId(),
                $queuedPayment->getType(),
                $queuedPayment->getAssocId()
            );
            if ($completed) {
                $completedId = (int) $completed->getId();
            }
        }

        $this->upsertPaymentRecord($contextId, [
            'queued_payment_id' => (int) $queuedPayment->getId(),
            'completed_payment_id' => $completedId,
            'user_id' => (int) $queuedPayment->getUserId(),
            'reference' => $reference,
            'paystack_id' => isset($data['id']) ? (string) $data['id'] : null,
            'status' => 'success',
            'amount' => (float) $queuedPayment->getAmount(),
            'currency' => strtoupper((string) $queuedPayment->getCurrencyCode()),
            'payload' => json_encode($data),
        ]);

        $this->sendPaidEmails($context, $queuedPayment, $reference, $data);
    }

    private function manageTransactions(Request $request): JSONMessage
    {
        $context = $request->getContext();
        if (!$context) {
            return new JSONMessage(false);
        }
        $this->ensureSchema();
        $rows = DB::table('paystack_payments')
            ->where('context_id', (int) $context->getId())
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get();

        $transactions = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $transactions[] = [
                'id' => (int) $row['paystack_payment_id'],
                'reference' => $row['reference'],
                'amountFormatted' => self::currencySymbol((string) $row['currency']) . number_format((float) $row['amount'], 2),
                'status' => $row['status'],
                'createdAt' => $row['created_at'],
                'canRefund' => in_array($row['status'], ['success', 'partial_refund'], true)
                    && ((float) $row['amount'] - (float) $row['refunded_amount']) > 0.009,
                'refundUrl' => $request->getRouter()->url($request, null, null, 'manage', null, [
                    'verb' => 'refund',
                    'plugin' => $this->getName(),
                    'category' => 'paymethod',
                    'reference' => $row['reference'],
                ]),
            ];
        }
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('transactions', $transactions);
        return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('transactions.tpl')));
    }

    private function manageTransactionDetails(Request $request): JSONMessage
    {
        $context = $request->getContext();
        $reference = trim((string) $request->getUserVar('reference'));
        if (!$context || $reference === '') {
            return new JSONMessage(false);
        }
        $record = $this->getPaymentRecordByReference((int) $context->getId(), $reference);
        if (!$record) {
            return new JSONMessage(false, __('common.notFound'));
        }
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'record' => $record,
            'currencySymbol' => self::currencySymbol((string) $record['currency']),
            'refundUrl' => $request->getRouter()->url($request, null, null, 'manage', null, [
                'verb' => 'refund',
                'plugin' => $this->getName(),
                'category' => 'paymethod',
                'reference' => $reference,
            ]),
        ]);
        return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('transactionDetails.tpl')));
    }

    private function manageRefund(Request $request): JSONMessage
    {
        $context = $request->getContext();
        $reference = trim((string) $request->getUserVar('reference'));
        if (!$context || $reference === '') {
            return new JSONMessage(false);
        }
        if ($request->getUserVar('doRefund')) {
            if (!$request->isPost() || !$request->checkCSRF()) {
                return new JSONMessage(false, __('form.csrfInvalid'));
            }
            $amountVar = $request->getUserVar('amount');
            $requested = ($amountVar !== null && trim((string) $amountVar) !== '')
                ? (float) $amountVar
                : null;
            $result = $this->performRefund($context, $reference, $requested);
            return new JSONMessage($result['ok'], $result['message']);
        }

        $record = $this->getPaymentRecordByReference((int) $context->getId(), $reference);
        if (!$record) {
            return new JSONMessage(false, __('common.notFound'));
        }
        $templateMgr = TemplateManager::getManager($request);
        $remaining = max(0, (float) $record['amount'] - (float) $record['refunded_amount']);
        $templateMgr->assign([
            'record' => $record,
            'remaining' => $remaining,
            'currencySymbol' => self::currencySymbol((string) $record['currency']),
            'refundUrl' => $request->getRouter()->url($request, null, null, 'manage', null, [
                'verb' => 'refund',
                'plugin' => $this->getName(),
                'category' => 'paymethod',
                'reference' => $reference,
            ]),
        ]);
        return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('refund.tpl')));
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private function performRefund($context, string $reference, ?float $amount): array
    {
        $contextId = (int) $context->getId();
        $record = $this->getPaymentRecordByReference($contextId, $reference);
        if (!$record || !in_array($record['status'], ['success', 'partial_refund'], true)) {
            return ['ok' => false, 'message' => __('plugins.paymethod.paystack.refund.notRefundable')];
        }
        $remaining = (float) $record['amount'] - (float) $record['refunded_amount'];
        if ($remaining <= 0) {
            return ['ok' => false, 'message' => __('plugins.paymethod.paystack.refund.noneRemaining')];
        }
        if ($amount === null) {
            $amount = $remaining;
        }
        if ($amount <= 0 || $amount - $remaining > 0.009) {
            return ['ok' => false, 'message' => __('plugins.paymethod.paystack.refund.invalidAmount')];
        }

        try {
            $this->client($contextId)->refund(
                $reference,
                abs($amount - $remaining) < 0.009 ? null : PaystackClient::toSubunit($amount)
            );
        } catch (Exception $e) {
            error_log('Paystack refund failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $newRefunded = (float) $record['refunded_amount'] + $amount;
        $status = $newRefunded + 0.009 >= (float) $record['amount'] ? 'refunded' : 'partial_refund';
        DB::table('paystack_payments')
            ->where('context_id', $contextId)
            ->where('reference', $reference)
            ->update([
                'refunded_amount' => $newRefunded,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        $this->sendRefundEmail($context, $record, $amount, $status);
        return ['ok' => true, 'message' => __('plugins.paymethod.paystack.refund.success')];
    }

    private function sendPaidEmails($context, QueuedPayment $queuedPayment, string $reference, array $data): void
    {
        $contextId = (int) $context->getId();
        $paymentManager = Application::getPaymentManager($context);
        $paymentName = method_exists($paymentManager, 'getPaymentName')
            ? $paymentManager->getPaymentName($queuedPayment)
            : __('common.payment');
        $amount = number_format((float) $queuedPayment->getAmount(), 2);
        $currency = strtoupper((string) $queuedPayment->getCurrencyCode());
        $payer = Repo::user()->get($queuedPayment->getUserId());

        $vars = [
            'paymentAmount' => $amount,
            'paymentCurrency' => $currency,
            'paymentReference' => $reference,
            'paymentName' => $paymentName,
            'paymentDate' => date('Y-m-d H:i'),
            'payerName' => $payer ? $payer->getFullName() : '',
            'payerEmail' => $payer ? $payer->getEmail() : '',
            'recipientName' => $payer ? $payer->getFullName() : '',
            'contextName' => $context->getLocalizedName(),
        ];

        if ($this->settingFlag($contextId, 'sendConfirmationEmail', true) && $payer) {
            $this->sendMailable(new PaymentConfirmation($context), $context, [$payer->getEmail() => $payer->getFullName()], $vars);
        }
        if ($this->settingFlag($contextId, 'sendAdminNotification', true) && $context->getData('contactEmail')) {
            $this->sendMailable(
                new PaymentConfirmationAdmin($context),
                $context,
                [$context->getData('contactEmail') => $context->getData('contactName')],
                $vars
            );
        }
    }

    private function notifyFailed($context, ?QueuedPayment $queuedPayment, string $reference, string $error): void
    {
        if (!$context || !$queuedPayment) {
            return;
        }
        $payer = Repo::user()->get($queuedPayment->getUserId());
        if (!$payer) {
            return;
        }
        $this->sendMailable(new PaymentFailed($context), $context, [$payer->getEmail() => $payer->getFullName()], [
            'recipientName' => $payer->getFullName(),
            'paymentReference' => $reference,
            'errorMessage' => $error,
            'contextName' => $context->getLocalizedName(),
        ]);
    }

    private function sendRefundEmail($context, array $record, float $amount, string $status): void
    {
        $payer = !empty($record['user_id']) ? Repo::user()->get((int) $record['user_id']) : null;
        if (!$payer) {
            return;
        }
        $this->sendMailable(new PaymentRefunded($context), $context, [$payer->getEmail() => $payer->getFullName()], [
            'recipientName' => $payer->getFullName(),
            'paymentAmount' => number_format($amount, 2),
            'paymentCurrency' => $record['currency'],
            'paymentReference' => $record['reference'],
            'refundStatus' => $status,
            'contextName' => $context->getLocalizedName(),
        ]);
    }

    private function sendMailable(object $mailable, $context, array $to, array $vars): void
    {
        try {
            $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
            $locale = $context->getPrimaryLocale();
            $mailable->from($context->getData('contactEmail'), $context->getData('contactName'));
            foreach ($to as $email => $name) {
                $mailable->to($email, $name);
            }
            if ($template) {
                $mailable->subject($template->getLocalizedData('subject', $locale));
                $mailable->body($template->getLocalizedData('body', $locale));
            }
            $mailable->addData($vars);
            Mail::send($mailable);
        } catch (\Throwable $e) {
            error_log('Paystack email failed: ' . $e->getMessage());
        }
    }

    private function showConfirmation(Request $request, QueuedPayment $queuedPayment, string $reference, array $data): void
    {
        $journal = $request->getJournal();
        $paymentManager = Application::getPaymentManager($journal);
        $templateMgr = TemplateManager::getManager($request);
        $currency = strtoupper((string) $queuedPayment->getCurrencyCode());
        $templateMgr->assign([
            'pageTitle' => 'plugins.paymethod.paystack.paymentConfirmation.title',
            'paymentName' => method_exists($paymentManager, 'getPaymentName')
                ? $paymentManager->getPaymentName($queuedPayment)
                : __('common.payment'),
            'amount' => (float) $queuedPayment->getAmount(),
            'currencyCode' => $currency,
            'currencySymbol' => self::currencySymbol($currency),
            'reference' => $reference,
            'continueUrl' => $queuedPayment->getRequestUrl() ?: $request->url(null, 'index'),
            'receiptUrl' => $request->url(null, 'payment', 'plugin', [$this->getName(), 'receipt'], [
                'reference' => $reference,
            ]),
            'historyUrl' => $request->url(null, 'payment', 'plugin', [$this->getName(), 'history']),
        ]);
        $templateMgr->display($this->getTemplateResource('paymentConfirmation.tpl'));
    }

    private function showMessage(Request $request, string $messageKey): void
    {
        TemplateManager::getManager($request)
            ->assign('message', $messageKey)
            ->display('frontend/pages/message.tpl');
    }

    private function webhookResponse(int $status, string $message): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => $status < 400, 'message' => $message]);
        exit;
    }

    private function client(int $contextId): PaystackClient
    {
        return new PaystackClient($this->getSecretKey($contextId));
    }

    private function getQueuedPayment(int $queuedPaymentId): ?QueuedPayment
    {
        if ($queuedPaymentId < 1) {
            return null;
        }
        $dao = DAORegistry::getDAO('QueuedPaymentDAO');
        $queuedPayment = $dao ? $dao->getById($queuedPaymentId) : null;
        return $queuedPayment instanceof QueuedPayment ? $queuedPayment : null;
    }

    private function ensureSchema(): void
    {
        try {
            (new PaystackSchemaMigration())->up();
        } catch (\Throwable $e) {
            error_log('Paystack schema ensure failed: ' . $e->getMessage());
        }
    }

    private function claimFulfillment(int $contextId, int $queuedPaymentId, string $reference): bool
    {
        $this->ensureSchema();
        try {
            DB::table('paystack_fulfillment_guards')->insert([
                'context_id' => $contextId,
                'queued_payment_id' => $queuedPaymentId,
                'reference' => $reference,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function claimWebhookEvent(int $contextId, string $event, string $reference): bool
    {
        try {
            DB::table('paystack_webhook_dedupe')->insert([
                'context_id' => $contextId,
                'event' => $event,
                'reference' => $reference,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function upsertPaymentRecord(int $contextId, array $fields): void
    {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');
        $fields['context_id'] = $contextId;
        $fields['updated_at'] = $now;
        $existing = DB::table('paystack_payments')
            ->where('context_id', $contextId)
            ->where('reference', $fields['reference'])
            ->first();
        if ($existing) {
            DB::table('paystack_payments')
                ->where('paystack_payment_id', $existing->paystack_payment_id)
                ->update($fields);
            return;
        }
        $fields['created_at'] = $now;
        if (!isset($fields['refunded_amount'])) {
            $fields['refunded_amount'] = 0;
        }
        DB::table('paystack_payments')->insert($fields);
    }

    private function getPaymentRecordByReference(int $contextId, string $reference): ?array
    {
        $this->ensureSchema();
        $row = DB::table('paystack_payments')
            ->where('context_id', $contextId)
            ->where('reference', $reference)
            ->first();
        return $row ? (array) $row : null;
    }

    private function paystackSignatureHeader(): string
    {
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strtolower((string) $name) === 'x-paystack-signature') {
                    return (string) $value;
                }
            }
        }
        return (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
    }

    private function settingFlag(int $contextId, string $name, bool $default): bool
    {
        $value = $this->getSetting($contextId, $name);
        if ($value === null || $value === '') {
            return $default;
        }
        return (bool) $value;
    }

    private function maskedOrEmpty(string $value): string
    {
        return $value === '' ? '' : self::SECRET_MASK;
    }

    private function isMaskedSecret(string $value): bool
    {
        return $value === '' || preg_match('/^\*+$/', $value) === 1;
    }
}
