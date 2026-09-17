<?php

return [
    'number_prefix' => env('INVOICE_NUMBER_PREFIX', 'CDH-'),
    'currency' => env('INVOICE_CURRENCY', 'EUR'),
    'locale' => env('INVOICE_LOCALE', 'en_GB'),
    'storage_disk' => env('INVOICE_STORAGE_DISK', 'local'),
];
