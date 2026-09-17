<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_profile_address_and_policy_acceptances(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();

        $payload = $this->validPayload();

        $response = $this->postJson('/api/v1/register', $payload);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\WelcomeRegistrationMail::class);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'aria@example.com')
            ->assertJsonPath('data.user.email_verified', true);

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::where('email', 'aria@example.com')->firstOrFail();

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'country' => 'Latvia',
            'postcode' => 'LV-1010',
        ]);
        $this->assertDatabaseCount('policy_acceptances', 2);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'soft_currency' => 100,
        ]);
        $this->assertDatabaseHas('tower_cards', [
            'user_id' => $user->id,
            'code' => 'ember-archer',
        ]);
    }

    public function test_registration_rejects_excluded_countries_and_missing_consent(): void
    {
        $payload = $this->validPayload();
        $payload['address']['country'] = 'Russia';
        $payload['terms_accepted'] = false;

        $response = $this->postJson('/api/v1/register', $payload);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['address.country', 'terms_accepted']]]);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_countries_endpoint_returns_allowed_countries_only(): void
    {
        $response = $this->getJson('/api/v1/registration/countries');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissing(['countries' => ['Russia']])
            ->assertJsonPath('data.excluded_countries.0', 'Sudan');

        $countries = $response->json('data.countries');
        $this->assertGreaterThan(170, count($countries));
        $this->assertNotContains('Afghanistan', $countries);
        $this->assertContains('Latvia', $countries);
    }

    public function test_signed_email_verification_marks_user_as_verified(): void
    {
        $user = User::factory()->create();
        $url = URL::temporarySignedRoute(
            'api.v1.email.verify',
            now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    private function validPayload(): array
    {
        return [
            'email' => 'aria@example.com',
            'password' => 'a-secure-password',
            'name' => 'Aria',
            'surname' => 'Stormwatch',
            'phone' => '+371 2000 0000',
            'date_of_birth' => '1995-04-12',
            'address' => [
                'street' => '14 Lantern Row, apt. 3',
                'city' => 'Riga',
                'country' => 'Latvia',
                'postcode' => 'LV-1010',
            ],
            'terms_accepted' => true,
        ];
    }
}
