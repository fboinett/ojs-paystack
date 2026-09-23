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
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;

class PaystackStagePlugin extends GenericPlugin
{
    /** @var bool */
    private $paymethodLoaded = false;

    /** @var bool */
    private $capturingAuthorDashboard = false;

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
     * Load the payment plugin once the request context is known.
     * Checking enabled() at register() is too early and skips authors.
     */
    public function ensurePaymethod($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request ? $request->getContext() : null;
        $contextId = $context ? (int) $context->getId() : null;
        if (!$this->getEnabled($contextId)) {
            return false;
        }
        if (!$this->paymethodLoaded) {
            $this->paymethodLoaded = true;
            PluginRegistry::loadCategory('paymethod', true);
        }
        $paystack = PluginRegistry::getPlugin('paymethod', 'PaystackPayment');
        if (!$paystack) {
            return false;
        }
        if (method_exists($paystack, 'registerStageFilter')) {
            $paystack->registerStageFilter($hookName, $args);
        }
        if ($hookName !== 'TemplateManager::display') {
            return false;
        }
        $template = (string) ($args[1] ?? '');
        if (
            strpos($template, 'authorDashboard.tpl') !== false
            && !$this->capturingAuthorDashboard
            && isset($args[0])
            && is_object($args[0])
        ) {
            $this->capturingAuthorDashboard = true;
            try {
                ob_start();
                $args[0]->display($template);
                $html = ob_get_clean();
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $this->capturingAuthorDashboard = false;
                error_log('Paystack author stage failed: ' . $e->getMessage());
                return false;
            }
            $this->capturingAuthorDashboard = false;
            if (!is_string($html) || strpos($html, 'id="stageTabs"') === false) {
                return false;
            }
            if (method_exists($paystack, 'injectPaymentStage')) {
                $html = $paystack->injectPaymentStage($html);
            }
            $args[2] = $html;
            return true;
        }
        if (method_exists($paystack, 'loadFrontendStyles')) {
            $paystack->loadFrontendStyles($hookName, $args);
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
