<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EndlessProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_wave_checkpoint_is_saved_and_reward_is_idempotent(): void
    {
        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 0, 'soft_currency' => 0]);
        Sanctum::actingAs($user);

        $checkpoint = [
            'run_id' => '8f4f6e2f-3d7d-4f30-8ed4-61ea2fcd4491',
            'wave' => 1,
            'defeated_enemies' => 4,
            'lives_remaining' => 19,
        ];

        $this->postJson('/api/v1/me/game/checkpoints', $checkpoint)
            ->assertOk()
            ->assertJsonPath('data.idempotent', false)
            ->assertJsonPath('data.checkpoint.reward', 12)
            ->assertJsonPath('data.wallet.soft_currency', 12);

        $this->postJson('/api/v1/me/game/checkpoints', $checkpoint)
            ->assertOk()
            ->assertJsonPath('data.idempotent', true)
            ->assertJsonPath('data.wallet.soft_currency', 12);

        $this->assertDatabaseCount('wave_checkpoints', 1);
        $this->assertDatabaseHas('endless_runs', [
            'user_id' => $user->id,
            'highest_wave' => 1,
            'soft_currency_earned' => 12,
        ]);
    }

    public function test_player_must_save_waves_in_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/game/checkpoints', [
            'run_id' => '8f4f6e2f-3d7d-4f30-8ed4-61ea2fcd4491',
            'wave' => 3,
            'defeated_enemies' => 10,
            'lives_remaining' => 20,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_endless_progress_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/game/progress')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
