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
            // getName() must stay the class name. OJS matches it to version.xml
            // when deciding whether this plugin is enabled. A custom name never loads.
            if (!$this->getEnabled()) {
                $this->setEnabled(true);
            }
            Hook::add('TemplateResource::getFilename', [$this, 'overrideAuthorDashboard']);
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
        if (!$this->paymethodLoaded) {
            $this->paymethodLoaded = true;
            PluginRegistry::loadCategory('paymethod', true);
        }
        $templateMgr = $args[0] ?? null;
        $template = (string) ($args[1] ?? '');
        $isAuthorDashboard = strpos($template, 'authorDashboard.tpl') !== false;
        $paystack = PluginRegistry::getPlugin('paymethod', 'PaystackPayment');
        if (!$isAuthorDashboard && $paystack && is_object($templateMgr) && method_exists($paystack, 'addPaymentStage')) {
            $paystack->addPaymentStage($templateMgr);
        }
        if ($paystack && method_exists($paystack, 'registerStageFilter')) {
            $paystack->registerStageFilter($hookName, $args);
        }
        if ($hookName === 'TemplateManager::display' && $paystack && method_exists($paystack, 'loadFrontendStyles')) {
            $paystack->loadFrontendStyles($hookName, $args);
        }
        if ($hookName === 'TemplateManager::display' && $isAuthorDashboard && is_object($templateMgr)) {
            if (method_exists($templateMgr, 'clearCompiledTemplate')) {
                $templateMgr->clearCompiledTemplate('authorDashboard/authorDashboard.tpl');
            }
            if ($paystack && method_exists($paystack, 'prepareAuthorTemplate')) {
                $paystack->prepareAuthorTemplate($templateMgr);
            }
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

    /**
     * OJS renders the author stage bar from authorDashboard.tpl.
     * Replace that template so Payment is a real list item, not a script.
     */
    public function overrideAuthorDashboard($hookName, $args)
    {
        $template = (string) ($args[1] ?? '');
        if ($template !== 'authorDashboard/authorDashboard.tpl') {
            return false;
        }
        $path = dirname(__FILE__) . '/templates/authorDashboard.tpl';
        if (file_exists($path)) {
            $args[0] = $path;
        }
        return false;
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
