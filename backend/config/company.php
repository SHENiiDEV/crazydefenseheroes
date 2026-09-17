<?php

return [
    'name' => env('COMPANY_NAME', env('COMAPANY_NAME', env('APP_NAME', 'Crazy Defense Heroes Ltd'))),
    'number' => env('COMPANY_NUMBER', env('COMAPNY_NUMBER', '14892341')),
    'address' => env('COMPANY_ADDRESS', '71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom'),
    'email' => env('COMPANY_EMAIL', env('MAIL_FROM_ADDRESS', 'info@crazydefensehereoes.co.uk')),
    'domain' => env('COMPANY_DOMAIN', 'crazydefensehereoes.co.uk'),
];
