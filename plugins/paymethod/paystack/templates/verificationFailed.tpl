{**
 * plugins/paymethod/paystack/templates/verificationFailed.tpl
 *
 * Copyright (c) 2026 Francis Kipruto
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *}
{include file="frontend/components/header.tpl" pageTitle=$pageTitle}

<div class="page">
	<h1>{translate key="plugins.paymethod.paystack.error.verificationFailed.title"}</h1>
	<p>{translate key="plugins.paymethod.paystack.error.verificationFailed"}</p>
	{if $reference}
		<p>
			<strong>{translate key="plugins.paymethod.paystack.transactions.reference"}:</strong>
			<code>{$reference|escape}</code>
		</p>
	{/if}
	<p>{translate key="plugins.paymethod.paystack.error.verificationFailed.help"}</p>
</div>

{include file="frontend/components/footer.tpl"}
