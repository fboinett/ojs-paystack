{**
 * plugins/paymethod/paystack/templates/paymentReceipt.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.paymethod.paystack.paymentReceipt.title"}

<div class="page page_paystack_payment">
	<div class="paystack-card">
		<h1>{translate key="plugins.paymethod.paystack.paymentReceipt.title"}</h1>
		<p>{$journalName|escape}</p>

		<dl class="paystack-dl">
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentReceipt.billedTo"}</dt>
				<dd>{$payerName|escape}<br>{$payerEmail|escape}</dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.transactions.reference"}</dt>
				<dd><code>{$record.reference|escape}</code></dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentReceipt.totalPaid"}</dt>
				<dd><strong>{$currencySymbol|escape}{$record.amount|string_format:"%.2f"} {$record.currency|escape}</strong></dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentHistory.status"}</dt>
				<dd>{$record.status|escape}</dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentHistory.date"}</dt>
				<dd>{$record.created_at|escape}</dd>
			</div>
		</dl>
	</div>
</div>

{include file="frontend/components/footer.tpl"}
