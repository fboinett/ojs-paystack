<?php

/**
 * @file plugins/paymethod/paystack/classes/PaystackSchemaMigration.php
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * @class PaystackSchemaMigration
 *
 * @brief Idempotent schema for webhook dedupe, fulfilment guards, and payment records.
 */

namespace APP\plugins\paymethod\paystack\classes;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PaystackSchemaMigration extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paystack_payments')) {
            Schema::create('paystack_payments', function (Blueprint $table) {
                $table->bigIncrements('paystack_payment_id');
                $table->bigInteger('context_id');
                $table->bigInteger('queued_payment_id')->nullable();
                $table->bigInteger('completed_payment_id')->nullable();
                $table->bigInteger('user_id')->nullable();
                $table->string('reference', 128);
                $table->string('paystack_id', 64)->nullable();
                $table->string('status', 32)->default('pending');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 8);
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->text('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
                $table->unique(['context_id', 'reference'], 'paystack_pay_ctx_ref_u');
                $table->index(['context_id', 'status'], 'paystack_pay_ctx_status_i');
                $table->index(['completed_payment_id'], 'paystack_pay_completed_i');
            });
        }

        if (!Schema::hasTable('paystack_webhook_dedupe')) {
            Schema::create('paystack_webhook_dedupe', function (Blueprint $table) {
                $table->bigIncrements('dedupe_id');
                $table->bigInteger('context_id');
                $table->string('event', 100);
                $table->string('reference', 128);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['context_id', 'event', 'reference'], 'paystack_wh_dedupe_u');
            });
        }

        if (!Schema::hasTable('paystack_fulfillment_guards')) {
            Schema::create('paystack_fulfillment_guards', function (Blueprint $table) {
                $table->bigIncrements('guard_id');
                $table->bigInteger('context_id');
                $table->bigInteger('queued_payment_id')->nullable();
                $table->string('reference', 128);
                $table->timestamp('created_at')->useCurrent();
                $table->unique(['context_id', 'reference'], 'paystack_guard_ctx_ref_u');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('paystack_fulfillment_guards');
        Schema::dropIfExists('paystack_webhook_dedupe');
        Schema::dropIfExists('paystack_payments');
    }
}
