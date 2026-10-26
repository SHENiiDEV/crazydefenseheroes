<?php

return [
    'api_url' => rtrim((string) env('COREFY_API_URL', 'https://api.sterling-pay.com'), '/'),
    'account_id' => env('COREFY_ACCOUNT_ID', ''),
    'api_key' => env('COREFY_API_KEY', ''),
    'webhook_secret' => env('COREFY_WEBHOOK_SECRET', ''),
    'test_mode' => (bool) env('COREFY_TEST_MODE', true),
    'service_eur' => env('COREFY_SERVICE_EUR', 'payment_card_eur_hpp'),
    'callback_url' => env('COREFY_CALLBACK_URL', ''),
    'frontend_url' => rtrim((string) env('FRONTEND_URL', env('APP_URL', 'http://localhost:6767')), '/'),
    'hpp_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('COREFY_HPP_HOSTS', 'api.sterling-pay.com'))))),
];
