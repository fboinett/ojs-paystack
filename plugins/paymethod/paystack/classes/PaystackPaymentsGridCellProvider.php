<?php

/**
 * @file plugins/paymethod/paystack/classes/PaystackPaymentsGridCellProvider.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackPaymentsGridCellProvider
 *
 * @brief Cell values for the Article column on the Payments report.
 */

namespace APP\plugins\paymethod\paystack\classes;

use APP\controllers\grid\subscriptions\PaymentsGridCellProvider;
use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\payment\ojs\OJSPaymentManager;
use PKP\controllers\grid\GridColumn;
use PKP\db\DAORegistry;

class PaystackPaymentsGridCellProvider extends PaymentsGridCellProvider
{
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        if ($column->getId() !== 'article') {
            return parent::getTemplateVarsFromRowColumn($row, $column);
        }

        $payment = $row->getData();
        $info = self::assocLink($this->_request, (int) $payment->getType(), (int) $payment->getAssocId());
        return ['label' => $info['html']];
    }

    /**
     * @return array{text:string,url:string,html:string}
     */
    public static function assocLink(Request $request, int $type, int $assocId): array
    {
        $empty = ['text' => '—', 'url' => '', 'html' => '—'];
        if ($assocId <= 0) {
            return $empty;
        }

        $articleTypes = [
            OJSPaymentManager::PAYMENT_TYPE_PUBLICATION,
            OJSPaymentManager::PAYMENT_TYPE_SUBMISSION,
            OJSPaymentManager::PAYMENT_TYPE_FASTTRACK,
            OJSPaymentManager::PAYMENT_TYPE_PURCHASE_ARTICLE,
        ];

        if (in_array($type, $articleTypes, true)) {
            $submission = Repo::submission()->get($assocId);
            if (!$submission) {
                return [
                    'text' => __('plugins.paymethod.paystack.payments.submissionGone', ['id' => $assocId]),
                    'url' => '',
                    'html' => htmlspecialchars(__('plugins.paymethod.paystack.payments.submissionGone', ['id' => $assocId]), ENT_QUOTES, 'UTF-8'),
                ];
            }
            $title = self::submissionTitle($submission);
            $url = $request->url(null, 'workflow', 'access', [$submission->getId()]);
            $text = $title . ' (#' . $submission->getId() . ')';
            $html = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                . '</a> <span class="pkp_help">#' . (int) $submission->getId() . '</span>';
            return ['text' => $text, 'url' => $url, 'html' => $html];
        }

        if ($type === OJSPaymentManager::PAYMENT_TYPE_PURCHASE_ISSUE) {
            $issueDao = DAORegistry::getDAO('IssueDAO');
            $issue = $issueDao ? $issueDao->getById($assocId) : null;
            if (!$issue) {
                return $empty;
            }
            $label = method_exists($issue, 'getIssueIdentification')
                ? (string) $issue->getIssueIdentification()
                : __('issue.issue') . ' #' . $assocId;
            $url = $request->url(null, 'issue', 'view', [$issue->getId()]);
            $html = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
            return ['text' => $label, 'url' => $url, 'html' => $html];
        }

        return $empty;
    }

    public static function submissionTitle($submission): string
    {
        $publication = $submission->getCurrentPublication();
        $title = '';
        if ($publication) {
            if (method_exists($publication, 'getLocalizedFullTitle')) {
                $title = trim((string) $publication->getLocalizedFullTitle());
            }
            if ($title === '' && method_exists($publication, 'getLocalizedTitle')) {
                $title = trim((string) $publication->getLocalizedTitle());
            }
        }
        if ($title === '') {
            $title = __('submission.submission') . ' #' . $submission->getId();
        }
        return $title;
    }
}
