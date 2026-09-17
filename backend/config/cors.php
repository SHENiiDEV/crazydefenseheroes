<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')),
    ))),
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_HEADERS', 'Accept,Authorization,Content-Type,X-Request-Id,Idempotency-Key,X-Payment-Signature')),
    ))),
    'exposed_headers' => ['X-Request-Id'],
    'max_age' => 0,
    'supports_credentials' => false,
];
