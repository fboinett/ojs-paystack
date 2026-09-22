{**
 * plugins/paymethod/paystack/templates/workflowStagePanel.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 * Payment stage panel: after Review, before Copyediting.
 *}
<div class="paystack-workflow">
	<h2>{translate key="plugins.paymethod.paystack.workflow.heading"}</h2>
	<p>{translate key="plugins.paymethod.paystack.workflow.intro"}</p>

	<div class="paystack-workflow__status paystack-workflow__status--{$paystackFeeStatus|escape}">
		<p>
			<strong>{translate key="plugins.paymethod.paystack.workflow.fee"}:</strong>
			{$paystackCurrencySymbol|escape}{$paystackFeeAmount|string_format:"%.2f"} {$paystackCurrency|escape}
		</p>
		<p>
			<strong>{translate key="plugins.paymethod.paystack.paymentHistory.status"}:</strong>
			{if $paystackFeeStatus == 'paid'}
				{translate key="plugins.paymethod.paystack.workflow.status.paid"}
			{elseif $paystackFeeStatus == 'waived'}
				{translate key="plugins.paymethod.paystack.workflow.status.waived"}
			{elseif $paystackFeeStatus == 'due'}
				{translate key="plugins.paymethod.paystack.workflow.status.due"}
			{else}
				{translate key="plugins.paymethod.paystack.workflow.status.waiting"}
			{/if}
		</p>
	</div>

	{if $paystackFeeStatus == 'due' && $paystackCanPay}
		<p>{translate key="plugins.paymethod.paystack.workflow.payHelp"}</p>
		<p>
			<a class="pkpButton" href="{$paystackPayUrl|escape}">
				{translate key="plugins.paymethod.paystack.paymentDetails.payNow"}
			</a>
		</p>
	{elseif $paystackFeeStatus == 'due' && !$paystackCanPay}
		<p>{translate key="plugins.paymethod.paystack.workflow.editorRequested"}</p>
	{elseif $paystackFeeStatus == 'waiting'}
		<p>{translate key="plugins.paymethod.paystack.workflow.waitingHelp"}</p>
	{elseif $paystackFeeStatus == 'paid'}
		<p>{translate key="plugins.paymethod.paystack.workflow.paidHelp"}</p>
	{/if}
</div>
