<?php

/**
 * @file plugins/paymethod/paystack/PaystackPaymentForm.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackPaymentForm
 *
 * @brief Reader-facing payment summary shown before redirecting to Paystack.
 */

namespace APP\plugins\paymethod\paystack;

use APP\core\Application;
use APP\core\Request;
use APP\plugins\paymethod\paystack\classes\ApcOwnerCompatibility;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\payment\QueuedPayment;

class PaystackPaymentForm extends Form
{
    /** @var PaystackPaymentPlugin */
    public $_plugin;

    /** @var QueuedPayment */
    public $_queuedPayment;

    public function __construct(PaystackPaymentPlugin $plugin, QueuedPayment $queuedPayment)
    {
        $this->_plugin = $plugin;
        $this->_queuedPayment = $queuedPayment;
        parent::__construct(null);
    }

    /**
     * @copydoc Form::display()
     *
     * @param null|Request $request
     * @param null|mixed $template
     */
    public function display($request = null, $template = null)
    {
        if (Config::getVar('general', 'sandbox', false)) {
            TemplateManager::getManager($request)
                ->assign('message', 'common.sandbox')
                ->display('frontend/pages/message.tpl');
            return;
        }

        $journal = $request->getJournal();
        $contextId = $journal ? (int) $journal->getId() : 0;
        $user = $request->getUser();

        if (!$this->_plugin->isHttpsRequest() && !$this->_plugin->isTestMode($contextId)) {
            TemplateManager::getManager($request)
                ->assign('message', 'plugins.paymethod.paystack.error.httpsRequired')
                ->display('frontend/pages/message.tpl');
            return;
        }

        $queuedPaymentDao = DAORegistry::getDAO('QueuedPaymentDAO');
        if (!$user || !ApcOwnerCompatibility::authorizeAndRepair($this->_queuedPayment, $user, $queuedPaymentDao)) {
            TemplateManager::getManager($request)
                ->assign('message', 'user.authorization.accessDenied')
                ->display('frontend/pages/message.tpl');
            return;
        }

        $paymentManager = Application::getPaymentManager($journal);
        $paymentName = method_exists($paymentManager, 'getPaymentName')
            ? $paymentManager->getPaymentName($this->_queuedPayment)
            : __('common.payment');

        $currencyCode = strtoupper((string) $this->_queuedPayment->getCurrencyCode());
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => 'common.payment',
            'paymentName' => $paymentName,
            'amount' => (float) $this->_queuedPayment->getAmount(),
            'currencyCode' => $currencyCode,
            'currencySymbol' => PaystackPaymentPlugin::currencySymbol($currencyCode),
            'testMode' => $this->_plugin->isTestMode($contextId),
            'initiatePaymentUrl' => $request->url(
                null,
                'payment',
                'plugin',
                [$this->_plugin->getName(), 'initiate'],
                ['queuedPaymentId' => $this->_queuedPayment->getId()]
            ),
            'cancelUrl' => $this->_queuedPayment->getRequestUrl() ?: $request->url(null, 'index'),
        ]);
        $templateMgr->display($this->_plugin->getTemplateResource('paymentDetails.tpl'));
    }
}
