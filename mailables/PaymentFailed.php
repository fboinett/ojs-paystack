<?php

/**
 * @file plugins/paymethod/paystack/mailables/PaymentFailed.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 */

namespace APP\plugins\paymethod\paystack\mailables;

use APP\journal\Journal;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\security\Role;

class PaymentFailed extends Mailable
{
    use Configurable;

    protected static ?string $name = 'mailable.paystack.paymentFailed.name';
    protected static ?string $description = 'mailable.paystack.paymentFailed.description';
    protected static ?string $emailTemplateKey = 'PAYSTACK_PAYMENT_FAILED';
    protected static array $toRoleIds = [Role::ROLE_ID_READER];

    public function __construct(Journal $context)
    {
        parent::__construct(func_get_args());
    }
}
