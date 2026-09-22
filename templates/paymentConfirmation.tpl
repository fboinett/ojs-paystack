{**
 * plugins/paymethod/paystack/templates/paymentConfirmation.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.paymethod.paystack.paymentConfirmation.title"}

<div class="page page_paystack_payment">
	<div class="paystack-card">
		<h1>{translate key="plugins.paymethod.paystack.paymentConfirmation.title"}</h1>
		<p>{translate key="plugins.paymethod.paystack.paymentConfirmation.successMessage"}</p>

		<dl class="paystack-dl">
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentDetails.paymentFor"}</dt>
				<dd>{$paymentName|escape}</dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentDetails.amount"}</dt>
				<dd><strong>{$currencySymbol|escape}{$amount|string_format:"%.2f"} {$currencyCode|escape}</strong></dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.transactions.reference"}</dt>
				<dd><code>{$reference|escape}</code></dd>
			</div>
		</dl>

		<div class="paystack-actions">
			<a href="{$continueUrl|escape}" class="cmp_button">
				{translate key="common.continue"}
			</a>
			<a href="{$receiptUrl|escape}" class="cmp_button cmp_button_outline">
				{translate key="plugins.paymethod.paystack.paymentHistory.viewReceipt"}
			</a>
			<a href="{$historyUrl|escape}" class="cmp_button cmp_button_outline">
				{translate key="plugins.paymethod.paystack.paymentHistory.viewHistory"}
			</a>
		</div>
	</div>
</div>

{include file="frontend/components/footer.tpl"}
