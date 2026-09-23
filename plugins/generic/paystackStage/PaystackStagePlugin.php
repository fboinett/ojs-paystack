<?php

/**
 * @file plugins/generic/paystackStage/PaystackStagePlugin.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackStagePlugin
 *
 * @brief Loads Paystack on workflow and author pages so the Payment stage
 * is available to editors and authors.
 */

namespace APP\plugins\generic\paystackStage;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;

class PaystackStagePlugin extends GenericPlugin
{
    /** @var bool */
    private $paymethodLoaded = false;

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) {
            $this->addLocaleData();
            Hook::add('LoadHandler', [$this, 'ensurePaymethod']);
            Hook::add('TemplateManager::display', [$this, 'ensurePaymethod']);
            Hook::add('TemplateManager::fetch', [$this, 'ensurePaymethod']);
        }
        return $success;
    }

    /**
     * The author dashboard does not load payment plugins. This plugin does,
     * then adds Payment to the same stage list the page already renders.
     */
    public function ensurePaymethod($hookName, $args)
    {
        if (!$this->getEnabled()) {
            return false;
        }
        if (!$this->paymethodLoaded) {
            $this->paymethodLoaded = true;
            PluginRegistry::loadCategory('paymethod', true);
        }
        $templateMgr = $args[0] ?? null;
        $paystack = PluginRegistry::getPlugin('paymethod', 'PaystackPayment');
        if ($paystack && is_object($templateMgr) && method_exists($paystack, 'addPaymentStage')) {
            $paystack->addPaymentStage($templateMgr);
        }
        if ($paystack && method_exists($paystack, 'registerStageFilter')) {
            $paystack->registerStageFilter($hookName, $args);
        }
        if ($hookName === 'TemplateManager::display' && $paystack && method_exists($paystack, 'loadFrontendStyles')) {
            $paystack->loadFrontendStyles($hookName, $args);
        }
        if (
            $hookName === 'TemplateManager::display'
            && is_object($templateMgr)
            && method_exists($templateMgr, 'addJavaScript')
        ) {
            $request = Application::get()->getRequest();
            $templateMgr->addJavaScript(
                'paystackAuthorStage',
                $request->getBaseUrl() . '/' . $this->getPluginPath() . '/js/authorStage.js?v=145',
                [
                    'contexts' => ['backend'],
                    'priority' => TemplateManager::STYLE_SEQUENCE_LAST,
                ]
            );
        }
        return false;
    }

    public function getName()
    {
        return 'paystackStage';
    }

    public function getDisplayName()
    {
        return __('plugins.generic.paystackStage.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.paystackStage.description');
    }
}
