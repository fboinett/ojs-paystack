{**
 * plugins/paymethod/paystack/templates/transactions.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
<div id="paystackTransactions">
	<h3>{translate key="plugins.paymethod.paystack.transactions.tab"}</h3>
	{if $transactions|@count}
		<table class="pkpTable">
			<thead>
				<tr>
					<th>{translate key="plugins.paymethod.paystack.paymentHistory.date"}</th>
					<th>{translate key="plugins.paymethod.paystack.payments.article"}</th>
					<th>{translate key="plugins.paymethod.paystack.transactions.reference"}</th>
					<th>{translate key="plugins.paymethod.paystack.transactions.amount"}</th>
					<th>{translate key="plugins.paymethod.paystack.paymentHistory.status"}</th>
					<th>{translate key="plugins.paymethod.paystack.paymentHistory.actions"}</th>
				</tr>
			</thead>
			<tbody>
				{foreach from=$transactions item=row}
					<tr>
						<td>{$row.createdAt|escape}</td>
						<td>{$row.articleHtml nofilter}</td>
						<td><code>{$row.reference|escape}</code></td>
						<td>{$row.amountFormatted|escape}</td>
						<td>{$row.status|escape}</td>
						<td>
							{if $row.canRefund}
								<form class="pkp_form" method="post" action="{$row.refundUrl|escape}" onsubmit="return confirm('Refund this Paystack payment?');">
									{csrf}
									<input type="hidden" name="doRefund" value="1">
									<button type="submit" class="pkp_button">{translate key="plugins.paymethod.paystack.refund.action"}</button>
								</form>
							{else}
								—
							{/if}
						</td>
					</tr>
				{/foreach}
			</tbody>
		</table>
	{else}
		<p>{translate key="common.none"}</p>
	{/if}
</div>
