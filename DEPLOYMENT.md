# Crazy Defense Hereoes - deployment checklist

## Before deployment

- Copy backend/.env.example to backend/.env.
- Generate a unique APP_KEY with php artisan key:generate.
- Set APP_ENV=production and APP_DEBUG=false.
- Replace all company placeholders with the legal company details.
- Set the real APP_URL, frontend API URL, and production CORS origin.
- Configure Namecheap Private Email SMTP credentials.
- Set a random PAYMENTS_WEBHOOK_SECRET.
- Treasury currently supports manual purchases without a payment provider. With `TOPUP_MANUAL_ENABLED=true`, confirming a purchase immediately credits the wallet and issues an invoice; no money is collected.
- Configure `TOPUP_DIAMONDS_PER_EURO=100` and `TOPUP_GOLD_PER_EURO=2000` to change the displayed prices. Only Laravel calculates the saved order total.
- Treasury supports EUR, GBP and USD reference-price orders. Configure GBP with `TOPUP_DIAMONDS_PER_GBP` / `TOPUP_GOLD_PER_GBP`, and USD with `TOPUP_DIAMONDS_PER_USD` / `TOPUP_GOLD_PER_USD`. These are merchant game prices, not automatic market FX conversions. Currency selection does not enable card processing.
- If enabling a payment provider later, disable manual purchases and map its success webhook to /api/v1/payments/webhooks/{provider}.
- Use private object storage for invoices and configure its credentials.

## Release checks

    cd backend
    composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan storage:link
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan cdh:check-production
    php artisan test

Build the frontend with npm ci and npm run build, then serve dist/ behind HTTPS.

## Runtime processes

Invoice PDFs are generated when an order is completed. Run a queue worker for receipt emails:

    php artisan queue:work --tries=3 --backoff=60

Use `MAIL_MAILER=smtp`, `MAIL_HOST=mail.privateemail.com`, `MAIL_PORT=587`, the mailbox username/password and the matching `MAIL_FROM_ADDRESS`. The `log` mailer does not deliver email.

After a release or configuration change, rebuild the frontend from the repository root, refresh Laravel's config/route/view caches, and run `php artisan queue:restart` so supervised workers load the new code and settings. Check `php artisan queue:failed` if an invoice remains pending in Treasury. After fixing SMTP, retry only the relevant failed job with `php artisan queue:retry <id>`.

Use a process supervisor, HTTPS termination, daily database backups, and monitored failed jobs. Do not commit .env, SMTP credentials, payment secrets, invoice storage credentials, or generated private invoices.

Legal documents are served from `config/legal.php` through both `/legal/{document}` and `/api/v1/legal/{document}`. Their current version is 2026-10-26. If the deployment overrides `TERMS_VERSION` or `PRIVACY_VERSION`, update those values to match before accepting new registrations. Rebuild Laravel configuration and restart workers after releases. The earlier chest-economy release changed future contents to fixed card/boost bundles without removing previously credited inventory; the newer hosted-checkout release requires the migration described below.

The modal's full-page links and policy-to-policy navigation now use `/api/v1/legal/{document}/page`. These public routes return complete HTML documents through the existing `/api/` backend mapping, so they do not depend on a separate `/legal/` server rule. JSON documents remain at `/api/v1/legal/{document}`. After deployment, open each full-page link from the modal and check that it shows the requested policy, not the SPA homepage or raw JSON.

The older `/legal/terms`, `/legal/privacy`, `/legal/refunds` and `/legal/security` routes remain available in Laravel. To support bookmarks to those paths, forward `/legal/` to Laravel as well; otherwise the web server's SPA fallback may return `dist/index.html` for them. No Nginx change is required for the new links as long as `/api/` already reaches Laravel.

The English responses to the earlier acquirer review are in `ACQUIRER_RESPONSE.md`. They describe the previous manual-mode release and must not be forwarded unchanged once hosted checkout is enabled. Deploy and check actual production behaviour before sending current payment statements. No production SMTP delivery or live processing is established by local test results.

## Sterling Pay / Corefy EUR hosted checkout

This integration uses the supplied Commerce Payment Invoice API, not the newer Payment Requests API. Only EUR has been confirmed enabled for this merchant. Corefy mode ignores `TOPUP_MANUAL_ENABLED`: it cannot be used to get free allocations. GBP and USD remain available only in the older manual mode, not in Sterling Pay checkout.

Set these in **backend/.env**, never in frontend `VITE_*` variables:

```dotenv
PAYMENTS_PROVIDER=corefy
TOPUP_MANUAL_ENABLED=false
COREFY_API_URL=https://api.sterling-pay.com
COREFY_ACCOUNT_ID=
COREFY_API_KEY=
COREFY_WEBHOOK_SECRET=
COREFY_TEST_MODE=true
COREFY_SERVICE_EUR=payment_card_eur_hpp
COREFY_HPP_HOSTS=api.sterling-pay.com
FRONTEND_URL=https://crazydefensehereoes.co.uk
COREFY_CALLBACK_URL=https://crazydefensehereoes.co.uk/api/v1/payments/corefy/webhook
```

Supply the matching test account credentials first, then matching live credentials and `COREFY_TEST_MODE=false` only after a successful provider test. Confirm the exact EUR service code with Sterling Pay. If their checkout link uses a different hostname, add only that verified exact hostname to `COREFY_HPP_HOSTS`; arbitrary redirects are rejected. The example keys above are deliberately empty. No provider request is made by local tests.

Checkout prefers an allowed HTTPS `hpp_url`, even when `flow_data.action` points to a different host. Only when no allowed HPP URL is available does it use an allowed GET flow action. For example, an HPP URL on `api.sterling-pay.com` works with the default allowlist even if the flow action is on `checkout.s-pay.finance`; do not broaden the allowlist just for that action. After deploying this backend fix, use **Check payment** on an existing pending order to recover a missing checkout link, then **Continue payment**. Retrying the same purchase with the same idempotency key also retrieves the linked invoice instead of creating another one. Pending status does not credit goods or issue a receipt. This fix alone needs neither frontend rebuilding nor an additional migration.

This release requires `php artisan migrate --force` for checkout and consent fields on existing orders. Then rebuild the frontend, refresh Laravel caches and restart the supervised queue worker. Update any `TERMS_VERSION` / `PRIVACY_VERSION` overrides to 2026-10-26. The policy service chooses hosted-checkout disclosures only when `PAYMENTS_PROVIDER=corefy`; earlier manual orders remain marked manual.

The browser's return URL uses the existing frontend root plus `?payment_return=<public order UUID>`, avoiding another SPA routing rule. A return parameter is only a routing hint. Treasury reads the account's orders and asks the authenticated backend to check payment. Full card details are entered at the hosted page; the backend sends the customer's identity, phone, date of birth and billing address with an ISO country code.

The callback URL accepts `X-Signature` with `base64(sha1(secret + raw body + secret, binary))`, including in local/test mode. It does not use the older `X-Payment-Signature` HMAC endpoint. Before crediting an order, the server independently retrieves the provider invoice and verifies reference, original and settled amounts, currency, invoice ID, test/live mode, `processed` status and `ok` resolution. Pending, failed and partial payments do not grant goods. Replays cannot grant a second time. Test-mode invoices are labelled as tests with no real funds collected.

If the initial POST times out, the same order is not blindly posted again: an invoice may already exist at Sterling Pay. Find the invoice in the provider dashboard and reconcile the single order:

```bash
php artisan cdh:reconcile-payment <public-order-uuid> --invoice=<provider-invoice-id>
```

For an order already linked to a provider invoice, omit `--invoice`. The command only reads the provider and applies the same verified delivery logic; it does not create a checkout or initiate a charge/refund. Refund notifications are recorded for support review and cannot re-credit goods; already consumed items are not automatically clawed back. Refund execution remains in the provider's merchant process, not this application.

Before a live launch, verify callback reachability through the proxy/WAF, the hosted URL allowlist, test/live key separation, one successful provider test, repeated callbacks, a declined payment, delayed callback reconciliation, customer receipt delivery and your actual tax/VAT treatment. The existing invoice tax calculation remains unchanged at zero; this integration does not implement a tax engine or establish that zero tax is appropriate for the company. Do not treat the integration or draft policies as tax/legal certification.
