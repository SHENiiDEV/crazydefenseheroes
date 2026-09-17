# Crazy Defense Heroes - deployment checklist

## Before deployment

- Copy backend/.env.example to backend/.env.
- Generate a unique APP_KEY with php artisan key:generate.
- Set APP_ENV=production and APP_DEBUG=false.
- Replace all company placeholders with the legal company details.
- Set the real APP_URL, frontend API URL, and production CORS origin.
- Configure Namecheap Private Email SMTP credentials.
- Set a random PAYMENTS_WEBHOOK_SECRET.
- Choose the payment provider and map its success webhook to /api/v1/payments/webhooks/{provider}.
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

Run a queue worker for invoice PDFs and email:

    php artisan queue:work --tries=3 --backoff=60

Use a process supervisor, HTTPS termination, daily database backups, and monitored failed jobs. Do not commit .env, SMTP credentials, payment secrets, invoice storage credentials, or generated private invoices.
