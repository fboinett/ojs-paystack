{**
 * plugins/paymethod/paystack/templates/refund.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
<div id="paystackRefund">
	<h3>{translate key="plugins.paymethod.paystack.refund.action"}</h3>
	<p>{translate key="plugins.paymethod.paystack.refund.remaining"}: <strong>{$currencySymbol|escape}{$remaining|string_format:"%.2f"} {$record.currency|escape}</strong></p>
	<form method="post" action="{$refundUrl|escape}">
		{csrf}
		<input type="hidden" name="doRefund" value="1">
		<input type="hidden" name="reference" value="{$record.reference|escape}">
		<label>
			{translate key="plugins.paymethod.paystack.refund.amount"}
			<input type="number" name="amount" step="0.01" min="0.01" max="{$remaining|escape}" value="{$remaining|string_format:"%.2f"}">
		</label>
		<p class="pkp_help">{translate key="plugins.paymethod.paystack.refund.amountHelp"}</p>
		<button type="submit" class="pkp_button pkp_button_offset">{translate key="plugins.paymethod.paystack.refund.action"}</button>
	</form>
</div>
