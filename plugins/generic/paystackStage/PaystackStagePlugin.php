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
        if ($hookName === 'TemplateManager::display') {
            $paystack = PluginRegistry::getPlugin('paymethod', 'PaystackPayment');
            if ($paystack && method_exists($paystack, 'loadFrontendStyles')) {
                $paystack->loadFrontendStyles($hookName, $args);
            }
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
