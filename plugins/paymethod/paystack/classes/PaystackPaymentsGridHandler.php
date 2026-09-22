<?php

/**
 * @file plugins/paymethod/paystack/classes/PaystackPaymentsGridHandler.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackPaymentsGridHandler
 *
 * @brief Adds an Article column to the journal Payments report.
 */

namespace APP\plugins\paymethod\paystack\classes;

use APP\controllers\grid\subscriptions\PaymentsGridHandler;
use PKP\controllers\grid\GridColumn;

class PaystackPaymentsGridHandler extends PaymentsGridHandler
{
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->addColumn(
            new GridColumn(
                'article',
                'plugins.paymethod.paystack.payments.article',
                null,
                null,
                new PaystackPaymentsGridCellProvider($request),
                ['html' => true]
            )
        );
    }
}
