<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'app' => config('app.name'),
                'environment' => config('app.env'),
                'api_version' => 'v1',
                'services' => [
                    'database' => $this->databaseStatus(),
                    'mail' => $this->mailStatus(),
                ],
                'company' => [
                    'name' => config('company.name'),
                    'email' => config('company.email'),
                ],
                'checked_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'unavailable';
        }
    }

    private function mailStatus(): string
    {
        $mailer = (string) config('mail.default');
        $host = (string) config("mail.mailers.{$mailer}.host", '');

        return $mailer === 'log' || $host !== '' ? 'configured' : 'not_configured';
    }
}
