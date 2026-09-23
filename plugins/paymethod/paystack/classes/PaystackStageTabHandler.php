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
use APP\facades\Repo;
use PKP\core\JSONMessage;
use PKP\handler\PKPHandler;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\AuthorDashboardAccessPolicy;
use PKP\security\authorization\PolicySet;
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
        $allowed = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        // Authors use the same rule as the rest of the author dashboard.
        $allowed->addPolicy(new AuthorDashboardAccessPolicy($request, $args, $roleAssignments));
        // Editors use the editorial submission rule.
        $allowed->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        $allowed->addPolicy(new PaystackParticipantPolicy());
        $this->addPolicy($allowed);
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

/**
 * Permit the submission's authors and editors even if a workflow-type check misses them.
 */
class PaystackParticipantPolicy extends AuthorizationPolicy
{
    public function __construct()
    {
        parent::__construct('user.authorization.accessDenied');
    }

    public function effect()
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            $submissionId = (int) $request->getUserVar('submissionId');
            $submission = $submissionId ? Repo::submission()->get($submissionId) : null;
        }
        $context = $request->getContext();
        if (!$user || !$submission || !$context) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        if ((int) $submission->getData('contextId') !== (int) $context->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }
        $contextId = (int) $submission->getData('contextId');
        if (in_array((int) $user->getId(), ApcOwnerCompatibility::assignedAuthorIds($submission), true)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }
        if ($user->hasRole([
            Role::ROLE_ID_SITE_ADMIN,
            Role::ROLE_ID_MANAGER,
            Role::ROLE_ID_SUB_EDITOR,
            Role::ROLE_ID_ASSISTANT,
        ], $contextId)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }
        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
