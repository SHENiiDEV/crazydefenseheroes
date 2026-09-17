<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_contains_active_products(): void
    {
        Product::create([
            'code' => 'diamonds-500',
            'name' => '500 Diamonds',
            'product_type' => 'diamonds',
            'grant_quantity' => 500,
            'price_minor' => 499,
            'currency' => 'EUR',
            'active' => true,
        ]);

        $this->getJson('/api/v1/catalog')
            ->assertOk()
            ->assertJsonPath('data.products.0.code', 'diamonds-500')
            ->assertJsonPath('data.products.0.price_minor', 499);
    }

    public function test_order_creation_is_idempotent(): void
    {
        $product = Product::create([
            'code' => 'boost-fortify',
            'name' => 'Fortify Boost',
            'product_type' => 'boost',
            'grant_key' => 'fortify',
            'grant_quantity' => 3,
            'price_minor' => 299,
            'currency' => 'EUR',
            'active' => true,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $payload = [
            'product_code' => $product->code,
            'quantity' => 1,
            'idempotency_key' => 'watch-order-0001',
        ];

        $first = $this->postJson('/api/v1/me/orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.idempotent', false)
            ->assertJsonPath('data.order.total_minor', 299);

        $this->postJson('/api/v1/me/orders', $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent', true)
            ->assertJsonPath('data.order.public_id', $first->json('data.order.public_id'));

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_sandbox_payment_fulfills_order_only_once(): void
    {
        $product = Product::create([
            'code' => 'diamonds-500',
            'name' => '500 Diamonds',
            'product_type' => 'diamonds',
            'grant_quantity' => 500,
            'price_minor' => 499,
            'currency' => 'EUR',
            'active' => true,
        ]);
        $user = User::factory()->create();
        $user->wallet()->create();
        Sanctum::actingAs($user);

        $order = $this->postJson('/api/v1/me/orders', [
            'product_code' => $product->code,
            'quantity' => 2,
            'idempotency_key' => 'watch-order-0002',
        ])->json('data.order');

        $this->postJson('/api/v1/me/orders/'.$order['id'].'/sandbox-pay')
            ->assertOk()
            ->assertJsonPath('data.order.status', 'paid');

        $this->postJson('/api/v1/me/orders/'.$order['id'].'/sandbox-pay')
            ->assertOk()
            ->assertJsonPath('data.order.status', 'paid');

        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'diamonds' => 1000,
        ]);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_webhook_replay_does_not_duplicate_a_grant(): void
    {
        $product = Product::create([
            'code' => 'tower-ember-archer',
            'name' => 'Ember Archer Card',
            'product_type' => 'tower_card',
            'grant_key' => 'ember-archer',
            'grant_quantity' => 1,
            'price_minor' => 199,
            'currency' => 'EUR',
            'active' => true,
        ]);
        $user = User::factory()->create();
        $user->wallet()->create();
        Sanctum::actingAs($user);
        $order = $this->postJson('/api/v1/me/orders', [
            'product_code' => $product->code,
            'quantity' => 1,
            'idempotency_key' => 'watch-order-0003',
        ])->json('data.order');
        $payload = [
            'event_id' => 'evt-0001',
            'type' => 'payment.succeeded',
            'order_id' => $order['public_id'],
            'payment_id' => 'pay-0001',
            'amount_minor' => 199,
            'currency' => 'EUR',
        ];

        $this->postJson('/api/v1/payments/webhooks/test-provider', $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent', false);
        $this->postJson('/api/v1/payments/webhooks/test-provider', $payload)
            ->assertOk()
            ->assertJsonPath('data.idempotent', true);

        $this->assertDatabaseHas('tower_cards', [
            'user_id' => $user->id,
            'code' => 'ember-archer',
            'quantity' => 1,
        ]);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }
}
