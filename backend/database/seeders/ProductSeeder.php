<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::upsert([
            [
                'code' => 'diamonds-500',
                'name' => '500 Diamonds',
                'product_type' => 'diamonds',
                'grant_key' => null,
                'grant_quantity' => 500,
                'price_minor' => 499,
                'currency' => 'EUR',
                'metadata' => json_encode(['category' => 'top_up']),
                'active' => true,
            ],
            [
                'code' => 'diamonds-1200',
                'name' => '1,200 Diamonds',
                'product_type' => 'diamonds',
                'grant_key' => null,
                'grant_quantity' => 1200,
                'price_minor' => 999,
                'currency' => 'EUR',
                'metadata' => json_encode(['category' => 'top_up', 'featured' => true]),
                'active' => true,
            ],
            [
                'code' => 'boost-fortify',
                'name' => 'Fortify Boost',
                'product_type' => 'boost',
                'grant_key' => 'fortify',
                'grant_quantity' => 3,
                'price_minor' => 299,
                'currency' => 'EUR',
                'metadata' => json_encode(['category' => 'boost', 'description' => 'Three stronger opening waves.']),
                'active' => true,
            ],
            [
                'code' => 'tower-ember-archer',
                'name' => 'Ember Archer Card',
                'product_type' => 'tower_card',
                'grant_key' => 'ember-archer',
                'grant_quantity' => 1,
                'price_minor' => 199,
                'currency' => 'EUR',
                'metadata' => json_encode(['category' => 'tower']),
                'active' => true,
            ],
        ], ['code'], [
            'name',
            'product_type',
            'grant_key',
            'grant_quantity',
            'price_minor',
            'currency',
            'metadata',
            'active',
        ]);
    }
}
