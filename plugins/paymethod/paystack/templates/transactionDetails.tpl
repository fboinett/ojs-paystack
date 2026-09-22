{**
 * plugins/paymethod/paystack/templates/transactionDetails.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
<div id="paystackTransactionDetails">
	<h3>{translate key="plugins.paymethod.paystack.transactionDetails"}</h3>
	<dl>
		<dt>{translate key="plugins.paymethod.paystack.transactions.reference"}</dt>
		<dd><code>{$record.reference|escape}</code></dd>
		<dt>{translate key="plugins.paymethod.paystack.transactions.amount"}</dt>
		<dd>{$currencySymbol|escape}{$record.amount|string_format:"%.2f"} {$record.currency|escape}</dd>
		<dt>{translate key="plugins.paymethod.paystack.refund.refunded"}</dt>
		<dd>{$currencySymbol|escape}{$record.refunded_amount|string_format:"%.2f"}</dd>
		<dt>{translate key="plugins.paymethod.paystack.paymentHistory.status"}</dt>
		<dd>{$record.status|escape}</dd>
		<dt>{translate key="plugins.paymethod.paystack.paymentHistory.date"}</dt>
		<dd>{$record.created_at|escape}</dd>
	</dl>
	{if $record.status == 'success' || $record.status == 'partial_refund'}
		<p><a href="{$refundUrl|escape}">{translate key="plugins.paymethod.paystack.refund.action"}</a></p>
	{/if}
</div>
