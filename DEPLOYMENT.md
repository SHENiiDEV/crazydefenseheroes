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

Legal documents are served from `config/legal.php` through both `/legal/{document}` and `/api/v1/legal/{document}`. Their current version is 2026-10-22. If the deployment overrides `TERMS_VERSION` or `PRIVACY_VERSION`, update those values to match before accepting new registrations. Rebuild Laravel configuration and restart workers after this release; no new database migration is required. The release changes future chest contents to fixed card/boost bundles; previously credited inventory is not removed.

When serving the frontend and backend behind the same domain, forward `/api/` **and** `/legal/` to Laravel. Do not let the SPA fallback return `dist/index.html` for public policy URLs. Verify `/legal/terms`, `/legal/privacy`, `/legal/refunds` and `/legal/security` after deployment. The modal loads documents through `/api/v1/legal/...`, but its full-page links require the `/legal/` routes too.

The English responses to the acquirer review are in `ACQUIRER_RESPONSE.md`. Deploy and check the actual production behaviour before forwarding them. No production SMTP delivery or live payment processing is established by the local test results.
