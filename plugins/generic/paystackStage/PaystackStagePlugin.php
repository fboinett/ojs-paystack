<?php

/**
 * @file plugins/generic/paystackStage/PaystackStagePlugin.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackStagePlugin
 *
 * @brief Loads the Paystack paymethod plugin on every editorial page so the
 * Payment stage appears for authors as well as editors.
 */

namespace APP\plugins\generic\paystackStage;

use PKP\plugins\GenericPlugin;
use PKP\plugins\PluginRegistry;

class PaystackStagePlugin extends GenericPlugin
{
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success && $this->getEnabled($mainContextId)) {
            $this->addLocaleData();
            PluginRegistry::loadCategory('paymethod');
        }
        return $success;
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
