# Paystack for OJS 3.4

Paystack payment gateway for **Open Journal Systems 3.4** (including **3.4.0.10**).

This repository contains **both** plugins in one download:

| Folder | OJS location | What it does |
|---|---|---|
| `plugins/paymethod/paystack/` | Payment plugin | Takes the money via Paystack |
| `plugins/generic/paystackStage/` | Generic plugin | Shows a **Payment** stage after Review, before Copyediting |

Do **not** install [Airix360/PaystackOJS](https://github.com/Airix360/PaystackOJS) on 3.4 — that project targets OJS 3.5 only.

| | |
|---|---|
| **OJS** | 3.4.0+ (written against 3.4.0.10) |
| **PHP** | 8.0.2+ |
| **License** | GPL-3.0-or-later |
| **Currencies** | NGN, USD, GHS, ZAR, KES, XOF |

## Install (one download)

1. Download **[ojs-paystack-plugins.zip](https://github.com/fboinett/ojs-paystack/releases/latest/download/ojs-paystack-plugins.zip)** from Releases.
2. On the server, unzip **into the OJS `plugins/` directory**:

   ```
   cd /path/to/ojs/plugins
   unzip ojs-paystack-plugins.zip
   ```

   You should now have:

   ```
   plugins/paymethod/paystack/index.php
   plugins/paymethod/paystack/version.xml
   plugins/generic/paystackStage/index.php
   plugins/generic/paystackStage/version.xml
   ```

3. From the OJS root, register both plugins:

   ```
   php lib/pkp/tools/installPluginVersion.php plugins/paymethod/paystack/version.xml
   php lib/pkp/tools/installPluginVersion.php plugins/generic/paystackStage/version.xml
   ```

4. In OJS:
   - **Settings → Website → Plugins → Payment Plugins** → enable **Paystack Fee Payment**
   - **Settings → Website → Plugins → Generic Plugins** → enable **Paystack Payment Stage**
5. **Settings → Distribution → Payments**:
   - Enable payments
   - Choose a supported currency
   - Select **Paystack Fee Payment**
   - Paste your Paystack test or live keys
6. **Payments → Payment Types → Author Fees** — set the Article Processing Charge to an amount greater than 0.

You can also clone this repository and copy the inner `plugins/` folders:

```
cp -a ojs-paystack/plugins/paymethod/paystack /path/to/ojs/plugins/paymethod/paystack
cp -a ojs-paystack/plugins/generic/paystackStage /path/to/ojs/plugins/generic/paystackStage
```

## Author fees (pay after review)

OJS charges the APC when the editor accepts the submission after review.

1. Complete peer review (or **Accept and Skip Review**).
2. Choose **Request publication fee** (do not waive unless the fee should be skipped).
3. Record the editorial decision.
4. The author opens the submission → **Payment** stage (after Review, before Copyediting). Status is **Pending Payment**. Authors see **Pay with Paystack**.
5. The submission stays out of **Copyediting** until that payment succeeds. The submissions list shows **Pending Payment** instead of Copyediting.

After a successful payment the author is returned to their **submissions dashboard**.

If **Record Decision** still errors after Request Payment, set **Settings → Distribution → DOIs → Automatic DOI Assignment** to **Upon publication** — that is a known OJS 3.4.0.10 bug when the article is not yet in an issue.

## Paystack dashboard

Copy the callback and webhook URLs from the Payments settings page into Paystack **Settings → API Keys & Webhooks**:

```
Callback:  {journalUrl}/payment/plugin/PaystackPayment/callback
Webhook:   {journalUrl}/payment/plugin/PaystackPayment/webhook
```

Use HTTPS in live mode.

## What it does

- Hosted Paystack checkout (card data never touches the journal)
- Server-side verification on the browser callback **and** the webhook
- HMAC-SHA512 webhook authentication
- Idempotent fulfilment so callback and webhook cannot double-complete a payment
- Test / live API keys with masked secrets
- Payer confirmation, failure, refund, and journal-contact emails
- Manager **Paystack transactions** list with refunds
- Payments report includes an **Article** column linking each fee to its submission
- Payment stage after Review for editors and authors (**Awaiting Payment** + **Pay now**)
- After payment, authors return to their submissions dashboard

## Manager tools

With the plugin enabled, **Settings → Website → Plugins → Paystack Fee Payment** includes:

- **Paystack transactions** — search, inspect, refund
- **Settings** — Distribution → Payments

## Support

Issues: [github.com/fboinett/ojs-paystack/issues](https://github.com/fboinett/ojs-paystack/issues)
