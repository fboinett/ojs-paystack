{**
 * plugins/paymethod/paystack/templates/paymentDetails.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
{include file="frontend/components/header.tpl" pageTitle="common.payment"}

<div class="page page_paystack_payment">
	<div class="paystack-card">
		<h1>{translate key="common.payment"}</h1>
		<p>{translate key="plugins.paymethod.paystack.paymentDetails.description"}</p>

		{if $testMode}
			<p class="paystack-test-banner">{translate key="plugins.paymethod.paystack.testMode.banner"}</p>
		{/if}

		<dl class="paystack-dl">
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentDetails.paymentFor"}</dt>
				<dd>{$paymentName|escape}</dd>
			</div>
			<div>
				<dt>{translate key="plugins.paymethod.paystack.paymentDetails.amount"}</dt>
				<dd><strong>{$currencySymbol|escape}{$amount|string_format:"%.2f"} {$currencyCode|escape}</strong></dd>
			</div>
		</dl>

		<form method="post" action="{$initiatePaymentUrl|escape}" class="paystack-actions">
			{csrf}
			<button type="submit" class="cmp_button">
				{translate key="plugins.paymethod.paystack.paymentDetails.payNow"}
			</button>
			<a href="{$cancelUrl|escape}" class="cmp_button cmp_button_outline">
				{translate key="common.cancel"}
			</a>
		</form>
	</div>
</div>

{include file="frontend/components/footer.tpl"}
