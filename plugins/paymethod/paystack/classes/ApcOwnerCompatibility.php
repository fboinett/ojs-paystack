<?php

/**
 * @file plugins/paymethod/paystack/classes/ApcOwnerCompatibility.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @brief OJS queues publication fees to the editor who requested them, then
 * emails the assigned author. Let that author pay. See pkp/pkp-lib#12885.
 */

namespace APP\plugins\paymethod\paystack\classes;

use APP\facades\Repo;
use APP\payment\ojs\OJSPaymentManager;
use PKP\db\DAORegistry;
use PKP\security\Role;

class ApcOwnerCompatibility
{
    public static function authorizeAndRepair($queuedPayment, $currentUser, $queuedPaymentDao): bool
    {
        if (!$queuedPayment || !$currentUser) {
            return false;
        }

        $currentUserId = (int) $currentUser->getId();
        $ownerUserId = (int) $queuedPayment->getUserId();
        $paymentType = (int) $queuedPayment->getType();

        if ($paymentType !== (int) OJSPaymentManager::PAYMENT_TYPE_PUBLICATION) {
            return $ownerUserId === $currentUserId;
        }

        $submission = Repo::submission()->get((int) $queuedPayment->getAssocId());
        if (!$submission || (int) $submission->getData('contextId') !== (int) $queuedPayment->getContextId()) {
            return false;
        }

        $assignedAuthorIds = self::assignedAuthorIds($submission);
        if (!in_array($currentUserId, $assignedAuthorIds, true)) {
            return false;
        }

        if ($ownerUserId !== $currentUserId) {
            $queuedPayment->setUserId($currentUserId);
            if (method_exists($queuedPaymentDao, 'updateObject')) {
                try {
                    $queuedPaymentDao->updateObject($queuedPayment->getId(), $queuedPayment);
                } catch (\Throwable $e) {
                    $queuedPaymentDao->updateObject($queuedPayment);
                }
            }
        }

        return true;
    }

    public static function assignedAuthorIds($submission): array
    {
        $ids = [];
        $dao = DAORegistry::getDAO('StageAssignmentDAO');
        if ($dao) {
            $result = null;
            if (method_exists($dao, 'getBySubmissionAndRoleId')) {
                $result = $dao->getBySubmissionAndRoleId($submission->getId(), Role::ROLE_ID_AUTHOR);
            } elseif (method_exists($dao, 'getBySubmissionAndRoleIds')) {
                $result = $dao->getBySubmissionAndRoleIds($submission->getId(), [Role::ROLE_ID_AUTHOR]);
            }
            if ($result) {
                if (method_exists($result, 'toArray')) {
                    foreach ($result->toArray() as $assignment) {
                        $ids[] = (int) $assignment->getUserId();
                    }
                } else {
                    while ($assignment = $result->next()) {
                        $ids[] = (int) $assignment->getUserId();
                    }
                }
            }
        }

        $publication = $submission->getCurrentPublication();
        if ($publication && method_exists($publication, 'getAuthors')) {
            foreach ((array) $publication->getAuthors() as $author) {
                if (method_exists($author, 'getUserId') && $author->getUserId()) {
                    $ids[] = (int) $author->getUserId();
                }
                $email = method_exists($author, 'getEmail') ? (string) $author->getEmail() : '';
                if ($email !== '') {
                    $user = Repo::user()->getByEmail($email);
                    if ($user) {
                        $ids[] = (int) $user->getId();
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private static function primaryAuthorEmail($submission): ?string
    {
        $publication = $submission->getCurrentPublication();
        if (!$publication) {
            return null;
        }
        if (method_exists($publication, 'getPrimaryAuthor')) {
            $author = $publication->getPrimaryAuthor();
            if ($author && method_exists($author, 'getEmail')) {
                return (string) $author->getEmail();
            }
        }
        if (method_exists($publication, 'getAuthors')) {
            foreach ((array) $publication->getAuthors() as $author) {
                if (method_exists($author, 'getPrimaryContact') && $author->getPrimaryContact()) {
                    return (string) $author->getEmail();
                }
            }
        }
        return null;
    }
}
