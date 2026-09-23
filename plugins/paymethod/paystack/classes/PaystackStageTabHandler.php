<?php

/**
 * @file plugins/paymethod/paystack/classes/PaystackStageTabHandler.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackStageTabHandler
 *
 * @brief Loads the Payment stage for authors and editors.
 */

namespace APP\plugins\paymethod\paystack\classes;

use APP\core\Application;
use PKP\core\JSONMessage;
use PKP\handler\PKPHandler;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;

class PaystackStageTabHandler extends PKPHandler
{
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ],
            ['fetch']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Return the payment stage markup. OJS reloads this each time the tab is opened.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     *
     * @return JSONMessage
     */
    public function fetch($args, $request)
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $plugin = PluginRegistry::getPlugin('paymethod', 'PaystackPayment');
        $html = '<div class="paystack-workflow"><h2>Payment</h2><p>Waiting for the editor to request payment</p></div>';
        if ($plugin && method_exists($plugin, 'renderStage')) {
            $html = $plugin->renderStage($request, $submission);
        }
        return new JSONMessage(true, $html);
    }
}
