<?php

return [
    'provider' => env('PAYMENTS_PROVIDER', 'sandbox'),
    'webhook_secret' => env('PAYMENTS_WEBHOOK_SECRET', ''),
];
