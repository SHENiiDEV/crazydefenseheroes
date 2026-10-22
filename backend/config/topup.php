<?php

return [
    'manual_enabled' => (bool) env('TOPUP_MANUAL_ENABLED', true),
    'currency' => 'EUR',
    'diamonds_per_euro' => max(1, (int) env('TOPUP_DIAMONDS_PER_EURO', 100)),
    'gold_per_euro' => max(1, (int) env('TOPUP_GOLD_PER_EURO', 2000)),
    'max_quantity' => 100000000,
    'currencies' => [
        'EUR' => ['diamonds_per_unit' => max(1, (int) env('TOPUP_DIAMONDS_PER_EURO', 100)), 'gold_per_unit' => max(1, (int) env('TOPUP_GOLD_PER_EURO', 2000))],
        'GBP' => ['diamonds_per_unit' => max(1, (int) env('TOPUP_DIAMONDS_PER_GBP', 120)), 'gold_per_unit' => max(1, (int) env('TOPUP_GOLD_PER_GBP', 2400))],
        'USD' => ['diamonds_per_unit' => max(1, (int) env('TOPUP_DIAMONDS_PER_USD', 90)), 'gold_per_unit' => max(1, (int) env('TOPUP_GOLD_PER_USD', 1800))],
    ],
];
