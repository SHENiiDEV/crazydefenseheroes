<?php

return [
    'manual_enabled' => (bool) env('TOPUP_MANUAL_ENABLED', true),
    'currency' => 'EUR',
    'diamonds_per_euro' => max(1, (int) env('TOPUP_DIAMONDS_PER_EURO', 100)),
    'gold_per_euro' => max(1, (int) env('TOPUP_GOLD_PER_EURO', 2000)),
    'max_quantity' => 100000000,
];
