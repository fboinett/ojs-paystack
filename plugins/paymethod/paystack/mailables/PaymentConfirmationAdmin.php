<?php

/**
 * @file plugins/paymethod/paystack/mailables/PaymentConfirmationAdmin.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 */

namespace APP\plugins\paymethod\paystack\mailables;

use APP\journal\Journal;
use PKP\mail\Mailable;
use PKP\mail\traits\Configurable;
use PKP\security\Role;

class PaymentConfirmationAdmin extends Mailable
{
    use Configurable;

    protected static ?string $name = 'mailable.paystack.paymentConfirmationAdmin.name';
    protected static ?string $description = 'mailable.paystack.paymentConfirmationAdmin.description';
    protected static ?string $emailTemplateKey = 'PAYSTACK_PAYMENT_CONFIRMATION_ADMIN';
    protected static array $toRoleIds = [Role::ROLE_ID_MANAGER];

    public function __construct(Journal $context)
    {
        parent::__construct(func_get_args());
    }
}
