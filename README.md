# Paystack for OJS 3.4

Paystack payment gateway plugin for **Open Journal Systems 3.4** (including **3.4.0.10**).

It is a 3.4-native plugin.

| | |
|---|---|
| **OJS** | 3.4.0+ (written against 3.4.0.10) |
| **PHP** | 8.0.2+ |
| **License** | GPL-3.0-or-later |
| **Currencies** | NGN, USD, GHS, ZAR, KES, XOF |

## What it does

- Hosted Paystack checkout (card data never touches the journal)
- Server-side verification on the browser callback **and** the webhook
- HMAC-SHA512 webhook authentication (`hash_equals`)
- Idempotent fulfilment so the callback and webhook cannot double-complete a payment
- Test / live API keys with masked secrets
- Payer confirmation, failure, refund, and journal-contact emails
- Manager **Paystack transactions** list with full or partial refunds
- Reader payment history and receipt pages
- **Submission Payment tab** after peer review, so authors can pay the APC before proofreading

## Author fees (pay after review, before proofreading)

OJS charges article processing fees at the **end of Review**, when the editor accepts the submission and sends it to Copyediting. Proofreading happens later in Production — so the author can pay before that work starts.

### 1. Turn on the fee

1. Enable this plugin and select **Paystack Fee Payment** under **Settings → Distribution → Payments**.
2. Open **Payments → Payment Types** (left menu after payments are enabled).
3. Under **Author Fees**, enter the **Article Processing Charge** (must be greater than 0) and save.

Until that amount is set, OJS will not show “Request publication fee” and this plugin’s Payment tab stays hidden.

### 2. Editor: request payment after review

1. Complete peer review.
2. Click **Send to Copyediting** (or **Accept**).
3. On the extra screen, choose **Request publication fee** (do not waive unless the fee should be skipped).
4. Record the editorial decision.

OJS emails the assigned author a payment link and creates a task notification.

### 3. Author: pay from the submission

1. Open the submission from the dashboard (or the email link).
2. Open the **Payment** tab.
3. Click **Pay with Paystack**.

After Paystack confirms the payment, the tab shows **Paid**. Copyediting can be in progress; start **Production / proofreading** only after the tab shows Paid (or Waived).

Editors can also mark the fee Paid or Waived from the **Payments** dropdown at the top of the workflow.

> **Note.** OJS currently queues the APC against the *editor* who requested it, then emails the *author*. This plugin lets the assigned author pay anyway and records the payment in their name.

## Install

1. Download the repository ZIP from GitHub (or clone it).
2. Unpack so the plugin lives at:

   ```
   plugins/paymethod/paystack/index.php
   plugins/paymethod/paystack/version.xml
   plugins/paymethod/paystack/PaystackPaymentPlugin.php
   ```

   The folder **must** be named `paystack`.
3. In OJS go to **Settings → Website → Plugins → Payment Plugins** and enable **Paystack Fee Payment**.
4. If the plugin does not appear after a manual copy, run once from the OJS root:

   ```
   php lib/pkp/tools/installPluginVersion.php plugins/paymethod/paystack/version.xml
   ```
5. Go to **Settings → Distribution → Payments**:
   - Enable payments
   - Choose a supported currency
   - Select **Paystack Fee Payment**
   - Paste your Paystack test or live keys

## Paystack dashboard

Copy the callback and webhook URLs shown on the Payments settings page into Paystack **Settings → API Keys & Webhooks**:

```
Callback:  {journalUrl}/payment/plugin/PaystackPayment/callback
Webhook:   {journalUrl}/payment/plugin/PaystackPayment/webhook
```

If your site does not use restful URLs, OJS will show the `index.php/...` form of those URLs — use exactly what the settings page prints.

Use **test keys** (`pk_test_…` / `sk_test_…`) until a test charge works, then switch to live keys and turn Test Mode off. Live mode requires HTTPS.

## Payment flow

1. A reader/author opens an OJS payment link.
2. They confirm the amount and click **Pay with Paystack**.
3. Paystack hosts checkout, then redirects back to the callback.
4. The plugin calls `GET /transaction/verify/{reference}` and re-checks amount, currency, and reference.
5. OJS marks the queued payment complete.
6. Paystack also posts `charge.success` to the webhook; the fulfilment guard makes that a no-op if the callback already finished.

## Refunds

Journal managers can open **Website → Plugins → Paystack Fee Payment → Paystack transactions** and issue a full or partial refund. The plugin caps refunds at the original amount.

## Requirements

- OJS 3.4.x with payments enabled
- PHP 8.0.2 or newer (8.1/8.2 are fine)
- `ext-json` and outbound HTTPS (OJS already ships Guzzle)
- A Paystack account eligible for the journal currency

## Not in this 3.4 build

- OJS 3.5 Laravel scheduler / `HasTaskScheduler`
- The 3.5-only editorial “Payment” companion tab
- MultiPay orchestration

Those belong in a 3.5 plugin. This repository is intentionally 3.4-only.

## Upgrade note

If you later move the journal to OJS 3.5, uninstall this plugin and switch to a 3.5 Paystack plugin. Do not run both.

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).
