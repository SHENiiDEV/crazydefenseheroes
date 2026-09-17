<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_v1_health_endpoint_returns_the_api_contract(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.api_version', 'v1')
            ->assertJsonPath('data.services.database', 'ok')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'status',
                    'app',
                    'environment',
                    'api_version',
                    'services' => ['database', 'mail'],
                    'company' => ['name', 'email'],
                    'checked_at',
                ],
            ]);
    }

    public function test_api_errors_use_the_unified_error_contract(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $response
            ->assertNotFound()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'ROUTE_NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested route was not found.')
            ->assertJsonStructure([
                'success',
                'error' => ['code', 'message', 'details'],
                'meta' => ['request_id'],
            ]);
    }
}
