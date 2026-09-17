<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_player_can_read_their_canonical_state(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 250, 'soft_currency' => 100]);
        $user->towerCards()->create([
            'code' => 'ember-archer',
            'level' => 2,
            'quantity' => 1,
            'is_equipped' => true,
        ]);
        $user->inventoryItems()->create([
            'item_type' => 'starter',
            'item_key' => 'welcome-pack',
            'quantity' => 1,
            'metadata' => ['source' => 'registration'],
        ]);
        $user->boosts()->create(['code' => 'first-watch', 'quantity' => 1]);
        $user->loadout()->create(['slots' => ['ember-archer']]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.wallet.diamonds', 250)
            ->assertJsonPath('data.inventory.tower_cards.0.code', 'ember-archer')
            ->assertJsonPath('data.inventory.boosts.0.code', 'first-watch')
            ->assertJsonPath('data.loadout.slots.0', 'ember-archer');
    }

    public function test_player_can_update_loadout_only_with_owned_towers(): void
    {
        $user = User::factory()->create();
        $user->towerCards()->create(['code' => 'ember-archer']);
        $user->loadout()->create(['slots' => ['ember-archer']]);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/me/loadout', ['slots' => ['ember-archer']])
            ->assertOk()
            ->assertJsonPath('data.loadout.slots.0', 'ember-archer');

        $this->putJson('/api/v1/me/loadout', ['slots' => ['locked-dragon']])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_state_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_verified_player_can_login_and_receive_a_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'aria@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'aria@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', $user->email);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_player_can_upgrade_tower_and_spend_currency(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 10, 'soft_currency' => 200]);
        $user->towerCards()->create(['code' => 'ember-archer', 'level' => 1, 'quantity' => 1, 'is_equipped' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/towers/upgrade', ['code' => 'ember-archer']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.soft_currency', 120) // 200 - 80 = 120
            ->assertJsonPath('data.inventory.tower_cards.0.level', 2);
    }

    public function test_player_can_open_chest_and_receive_loot(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 0, 'soft_currency' => 200]);
        $user->towerCards()->create(['code' => 'ember-archer', 'level' => 1, 'quantity' => 1, 'is_equipped' => true]);
        $user->loadout()->create(['slots' => ['ember-archer']]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/chests/open', ['chest_id' => 'bronze']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.loot.card_code', 'ember-archer')
            ->assertJsonPath('data.loot.new_level', 2)
            ->assertJsonPath('data.player.inventory.tower_cards.0.level', 2);
        $this->assertGreaterThan(0, $response->json('data.loot.coins'));
    }

    public function test_player_can_topup_wallet(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 0, 'soft_currency' => 100]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/wallet/topup', ['package_code' => 'free-demo-100']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.wallet.diamonds', 100)
            ->assertJsonPath('data.wallet.soft_currency', 300);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TopUpReceiptMail::class);
    }

    public function test_company_endpoint_returns_company_details(): void
    {
        $response = $this->getJson('/api/v1/company');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'info@crazydefenseheroes.co.uk')
            ->assertJsonPath('data.number', '14892341');
    }
}
