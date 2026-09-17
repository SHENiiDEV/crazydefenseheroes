<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\EndlessCheckpointRequest;
use App\Models\EndlessRun;
use App\Models\User;
use App\Models\WaveCheckpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class EndlessProgressController
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $run = $user->endlessRuns()->latest('updated_at')->first();

        return response()->json([
            'success' => true,
            'data' => [
                'run' => $run,
                'wallet' => $user->wallet,
            ],
        ]);
    }

    public function checkpoint(EndlessCheckpointRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $result = DB::transaction(function () use ($user, $data): array {
            $run = EndlessRun::query()
                ->where('user_id', $user->id)
                ->where('client_run_id', $data['run_id'])
                ->lockForUpdate()
                ->first();

            if (! $run) {
                $run = EndlessRun::create([
                    'user_id' => $user->id,
                    'client_run_id' => $data['run_id'],
                    'lives_remaining' => $data['lives_remaining'],
                ]);
            }

            if ($data['wave'] > $run->highest_wave + 1) {
                throw ValidationException::withMessages([
                    'wave' => ['The next wave checkpoint is required before this wave can be saved.'],
                ]);
            }

            $checkpoint = $run->checkpoints()
                ->where('wave', $data['wave'])
                ->first();

            if ($checkpoint) {
                return [
                    'run' => $run->fresh(),
                    'checkpoint' => $checkpoint,
                    'wallet' => $user->wallet,
                    'idempotent' => true,
                ];
            }

            $reward = $this->rewardForWave($data['wave']);
            $checkpoint = WaveCheckpoint::create([
                'user_id' => $user->id,
                'endless_run_id' => $run->id,
                'wave' => $data['wave'],
                'defeated_enemies' => $data['defeated_enemies'],
                'lives_remaining' => $data['lives_remaining'],
                'reward' => $reward,
            ]);

            $run->update([
                'highest_wave' => max($run->highest_wave, $data['wave']),
                'defeated_enemies' => max($run->defeated_enemies, $data['defeated_enemies']),
                'lives_remaining' => $data['lives_remaining'],
                'soft_currency_earned' => $run->soft_currency_earned + $reward,
            ]);

            $wallet = $user->wallet()->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = $user->wallet()->create();
            }
            $wallet->increment('soft_currency', $reward);

            return [
                'run' => $run->fresh(),
                'checkpoint' => $checkpoint,
                'wallet' => $wallet->fresh(),
                'idempotent' => false,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    private function rewardForWave(int $wave): int
    {
        return min(1000, 10 + ($wave * 2));
    }
}
