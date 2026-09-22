{**
 * plugins/paymethod/paystack/templates/paymentHistory.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
{include file="frontend/components/header.tpl" pageTitle="plugins.paymethod.paystack.paymentHistory.title"}

<div class="page page_paystack_payment">
	<div class="paystack-card">
		<h1>{translate key="plugins.paymethod.paystack.paymentHistory.title"}</h1>
		<p>{translate key="plugins.paymethod.paystack.paymentHistory.description"}</p>

		{if $payments|@count}
			<table class="paystack-table">
				<thead>
					<tr>
						<th>{translate key="plugins.paymethod.paystack.paymentHistory.date"}</th>
						<th>{translate key="plugins.paymethod.paystack.transactions.reference"}</th>
						<th>{translate key="plugins.paymethod.paystack.paymentHistory.amount"}</th>
						<th>{translate key="plugins.paymethod.paystack.paymentHistory.status"}</th>
						<th>{translate key="plugins.paymethod.paystack.paymentHistory.actions"}</th>
					</tr>
				</thead>
				<tbody>
					{foreach from=$payments item=payment}
						<tr>
							<td>{$payment.createdAt|escape}</td>
							<td><code>{$payment.reference|escape}</code></td>
							<td>{$payment.amount|string_format:"%.2f"} {$payment.currency|escape}</td>
							<td>{$payment.status|escape}</td>
							<td><a href="{$payment.receiptUrl|escape}">{translate key="plugins.paymethod.paystack.paymentHistory.viewReceipt"}</a></td>
						</tr>
					{/foreach}
				</tbody>
			</table>
		{else}
			<p>{translate key="plugins.paymethod.paystack.paymentHistory.empty"}</p>
		{/if}
	</div>
</div>

{include file="frontend/components/footer.tpl"}
