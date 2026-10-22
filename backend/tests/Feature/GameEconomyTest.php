<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GameEconomyTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('chests')]
    public function test_chest_deducts_price_and_persists_exact_advertised_rewards(string $id, int $gold, int $diamonds, string $card, string $boost, int $charges): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['soft_currency' => 1000, 'diamonds' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/chests/open', ['chest_id' => $id])->assertOk()
            ->assertJsonPath('data.player.wallet.soft_currency', 1000 - $gold)
            ->assertJsonPath('data.player.wallet.diamonds', 100 - $diamonds)
            ->assertJsonPath('data.loot.coins', 0)->assertJsonPath('data.loot.diamonds', 0)
            ->assertJsonPath('data.loot.boost_quantity', $charges)->assertJsonPath('data.loot.was_new', true);

        $this->assertDatabaseHas('tower_cards', ['user_id' => $user->id, 'code' => $card, 'level' => 1]);
        $this->assertDatabaseHas('boosts', ['user_id' => $user->id, 'code' => $boost, 'quantity' => $charges]);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $user->id, 'type' => 'chest', 'soft_currency_delta' => -$gold, 'diamonds_delta' => -$diamonds]);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.inventory.tower_cards.0.code', $card)
            ->assertJsonPath('data.inventory.boosts.0.quantity', $charges)->assertJsonPath('data.loadout.slots.0', $card);
    }

    public static function chests(): array
    {
        return [
            'bronze' => ['bronze', 50, 0, 'ember-archer', 'speed-burst', 1],
            'silver' => ['silver', 120, 0, 'stone-warden', 'fortify', 2],
            'gold' => ['gold', 250, 10, 'vault-mage', 'meteor-strike', 3],
        ];
    }

    public function test_bronze_stash_cannot_generate_gold_by_repeated_opening(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['soft_currency' => 100, 'diamonds' => 0]);
        $user->towerCards()->create(['code' => 'ember-archer', 'level' => 1, 'quantity' => 1]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/chests/open', ['chest_id' => 'bronze'])->assertOk()->assertJsonPath('data.loot.new_level', 2);
        $this->postJson('/api/v1/me/chests/open', ['chest_id' => 'bronze'])->assertOk()->assertJsonPath('data.player.wallet.soft_currency', 0);
        $this->postJson('/api/v1/me/chests/open', ['chest_id' => 'bronze'])->assertUnprocessable();

        $this->assertDatabaseHas('tower_cards', ['user_id' => $user->id, 'code' => 'ember-archer', 'level' => 3]);
        $this->assertDatabaseCount('wallet_transactions', 2);
    }

    public function test_gold_chest_requires_diamonds_and_does_not_grant_items_on_failure(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['soft_currency' => 1000, 'diamonds' => 9]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/chests/open', ['chest_id' => 'gold'])->assertUnprocessable();

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'soft_currency' => 1000, 'diamonds' => 9]);
        $this->assertDatabaseCount('tower_cards', 0);
        $this->assertDatabaseCount('boosts', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_diamonds_can_be_spent_on_an_owned_tower_upgrade(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['soft_currency' => 80, 'diamonds' => 20]);
        $user->towerCards()->create(['code' => 'stone-warden', 'level' => 2]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/towers/upgrade', ['code' => 'stone-warden', 'currency' => 'diamonds'])->assertOk()
            ->assertJsonPath('data.wallet.diamonds', 10)->assertJsonPath('data.wallet.soft_currency', 80);

        $this->assertDatabaseHas('tower_cards', ['user_id' => $user->id, 'code' => 'stone-warden', 'level' => 3]);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $user->id, 'type' => 'upgrade', 'diamonds_delta' => -10]);
    }

    public function test_locked_tower_cannot_be_unlocked_by_the_upgrade_endpoint(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['soft_currency' => 1000]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/towers/upgrade', ['code' => 'vault-mage'])->assertUnprocessable();

        $this->assertDatabaseCount('tower_cards', 0);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'soft_currency' => 1000]);
    }

    public function test_boost_activation_consumes_one_charge_and_stops_at_zero(): void
    {
        $user = User::factory()->create();
        $user->boosts()->create(['code' => 'fortify', 'quantity' => 1]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/boosts/use', ['code' => 'fortify'])->assertOk()->assertJsonPath('data.inventory.boosts.0.quantity', 0);
        $this->postJson('/api/v1/me/boosts/use', ['code' => 'fortify'])->assertUnprocessable();

        $this->assertDatabaseHas('boosts', ['user_id' => $user->id, 'code' => 'fortify', 'quantity' => 0]);
    }

    public function test_chest_actions_require_authentication(): void
    {
        $this->postJson('/api/v1/me/chests/open', ['chest_id' => 'silver'])->assertUnauthorized();
    }

    public function test_failed_run_cannot_claim_a_completed_wave_reward(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['soft_currency' => 100]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/game/checkpoints', ['run_id' => '8f4f6e2f-3d7d-4f30-8ed4-61ea2fcd4491', 'wave' => 1, 'defeated_enemies' => 0, 'lives_remaining' => 0])->assertUnprocessable();

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'soft_currency' => 100]);
        $this->assertDatabaseCount('wave_checkpoints', 0);
    }
}
