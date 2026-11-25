<?php

namespace Tests\Feature;

use App\Mail\PurchaseReceipt;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorefyGbpPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.provider' => 'corefy', 'corefy.account_id' => 'eur-account', 'corefy.api_key' => 'eur-api-key',
            'corefy.webhook_secret' => 'eur-secret', 'corefy.test_mode' => true,
            'corefy.api_url' => 'https://api.sterling-pay.com', 'corefy.service_eur' => 'payment_card_eur_hpp',
            'corefy.hpp_hosts' => ['api.sterling-pay.com'], 'corefy.frontend_url' => 'https://game.example.test',
            'corefy.gbp' => ['enabled' => true, 'account_id' => 'gbp-account', 'api_key' => 'gbp-api-key',
                'webhook_secret' => 'gbp-secret', 'test_mode' => true, 'service' => 'payment_card_gbp_hpp',
                'api_url' => 'https://api.sterling-pay.com', 'callback_url' => '', 'hpp_hosts' => ['api.sterling-pay.com']],
        ]);
        Http::preventStrayRequests();
    }

    public function test_gbp_checkout_uses_separate_credentials_service_prices_and_callback_without_crediting_goods(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->player());
        config(['corefy.api_key' => '', 'corefy.account_id' => '', 'corefy.webhook_secret' => '']);
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_gbp', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/gbp']]], 201)]);

        $first = $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.currency', 'GBP')->assertJsonPath('data.order.total_minor', 200)
            ->assertJsonPath('data.order.status', 'pending')->assertJsonPath('data.order.payment_test_mode', true)
            ->assertJsonPath('data.wallet.diamonds', 10);
        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.id', $first->json('data.order.id'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('gbp-account:gbp-api-key'))
            && $request['data']['attributes']['currency'] === 'GBP' && $request['data']['attributes']['amount'] === 2
            && $request['data']['attributes']['service'] === 'payment_card_gbp_hpp'
            && str_ends_with($request['data']['attributes']['callback_url'], '/api/v1/payments/corefy/gbp/webhook'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
        Mail::assertNothingSent();
    }

    public function test_eur_checkout_keeps_its_existing_account_when_gbp_is_enabled(): void
    {
        Sanctum::actingAs($this->player());
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_eur', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/eur']]], 201)]);

        $this->postJson('/api/v1/me/topup', [...$this->purchase(), 'currency' => 'EUR'])->assertOk()
            ->assertJsonPath('data.order.currency', 'EUR')->assertJsonPath('data.order.total_minor', 240);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('eur-account:eur-api-key'))
            && $request['data']['attributes']['service'] === 'payment_card_eur_hpp'
            && str_ends_with($request['data']['attributes']['callback_url'], '/api/v1/payments/corefy/webhook'));
    }

    public function test_available_currencies_include_gbp_and_per_currency_mode_but_never_credentials(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.test_mode' => false]);

        $this->getJson('/api/v1/me/topup')->assertOk()->assertJsonCount(2, 'data.currencies')
            ->assertJsonPath('data.currencies.EUR.test_mode', false)->assertJsonPath('data.currencies.GBP.test_mode', true)
            ->assertJsonPath('data.currencies.GBP.diamonds_per_unit', 120)
            ->assertJsonPath('data.currencies.GBP.gold_per_unit', 2400)
            ->assertDontSee('gbp-api-key')->assertDontSee('gbp-secret')->assertDontSee('eur-api-key');

        Http::assertNothingSent();
    }

    public function test_disabled_gbp_is_hidden_and_returns_422_without_creating_an_order(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.gbp.enabled' => false]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertUnprocessable();
        $this->getJson('/api/v1/me/topup')->assertOk()->assertJsonCount(1, 'data.currencies');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('requiredGbpSettings')]
    public function test_missing_gbp_settings_return_503_instead_of_falling_back_to_eur(string $key): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.gbp.'.$key => '']);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public static function requiredGbpSettings(): array
    {
        return ['account' => ['account_id'], 'key' => ['api_key'], 'signature secret' => ['webhook_secret'], 'service' => ['service']];
    }

    public function test_gbp_callback_verifies_with_gbp_account_and_delivers_one_gbp_invoice_and_receipt(): void
    {
        Storage::fake('local');
        Mail::fake();
        config(['corefy.test_mode' => false]);
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_gbp' => Http::response($this->invoice($order))]);

        $this->sendCallback($order)->assertOk();
        $this->sendCallback($order)->assertOk();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('gbp-account:gbp-api-key')));
        Http::assertNotSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('eur-account:eur-api-key')));
        $this->assertDatabaseHas('wallets', ['user_id' => $order->user_id, 'diamonds' => 130, 'soft_currency' => 2400]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('invoices', ['order_id' => $order->id, 'currency' => 'GBP', 'total_minor' => 200]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('invoices', 1);
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseReceipt::class, function (PurchaseReceipt $mail): bool {
            $mail->assertSeeInHtml('2.00 GBP');

            return str_starts_with(Storage::disk('local')->get($mail->invoice->pdf_path), '%PDF-') && count($mail->attachments()) === 1;
        });
    }

    #[DataProvider('wrongCallbackSignatures')]
    public function test_other_currency_signature_returns_401_before_contacting_the_provider(string $endpoint, string $secret): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, $endpoint, $secret)->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public static function wrongCallbackSignatures(): array
    {
        return ['EUR key on GBP endpoint' => ['/api/v1/payments/corefy/gbp/webhook', 'eur-secret'],
            'GBP key on EUR endpoint' => ['/api/v1/payments/corefy/webhook', 'gbp-secret']];
    }

    public function test_eur_callback_cannot_deliver_a_gbp_order_even_with_a_valid_eur_signature(): void
    {
        $order = $this->pendingOrder();

        $this->sendCallback($order, '/api/v1/payments/corefy/webhook', 'eur-secret')->assertNotFound();

        Http::assertNothingSent();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_disabling_new_gbp_purchases_does_not_block_delivery_of_an_existing_paid_invoice(): void
    {
        Storage::fake('local');
        Mail::fake();
        $order = $this->pendingOrder();
        config(['corefy.gbp.enabled' => false]);
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_gbp' => Http::response($this->invoice($order))]);

        $this->sendCallback($order)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseHas('wallets', ['user_id' => $order->user_id, 'diamonds' => 130, 'soft_currency' => 2400]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertSentCount(1);
    }

    public function test_gbp_reconciliation_rejects_an_invoice_in_eur_without_crediting_goods(): void
    {
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_gbp' => Http::response($this->invoice($order, ['currency' => 'EUR']))]);

        $this->sendCallback($order)->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_gbp_pending_status_recovers_link_with_gbp_credentials_and_does_not_credit_goods(): void
    {
        $order = $this->pendingOrder();
        Sanctum::actingAs($order->user);
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_gbp' => Http::response($this->invoice($order, [
            'status' => 'process_pending', 'processed_amount' => 0, 'hpp_url' => 'https://api.sterling-pay.com/hpp/gbp',
        ]))]);

        $this->postJson('/api/v1/me/orders/'.$order->id.'/payment-status')->assertOk()
            ->assertJsonPath('data.order.checkout_url', 'https://api.sterling-pay.com/hpp/gbp')
            ->assertJsonPath('data.order.status', 'pending')->assertJsonPath('data.wallet.diamonds', 10);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('gbp-account:gbp-api-key')));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_gbp_can_use_a_separate_api_and_checkout_host(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.gbp.api_url' => 'https://gbp-api.example.test', 'corefy.gbp.hpp_hosts' => ['gbp-pay.example.test']]);
        Http::fake(['https://gbp-api.example.test/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_gbp', 'attributes' => ['hpp_url' => 'https://gbp-pay.example.test/hpp/test']]], 201)]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.checkout_url', 'https://gbp-pay.example.test/hpp/test');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gbp-api.example.test/payment-invoices'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('gbp-account:gbp-api-key')));
    }

    public function test_gbp_rejects_a_checkout_host_that_is_only_allowed_for_eur(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.gbp.hpp_hosts' => ['gbp-pay.example.test']]);
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_gbp', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/gbp']]], 201)]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();

        $this->assertDatabaseHas('orders', ['currency' => 'GBP', 'provider_payment_id' => 'cpi_gbp', 'checkout_url' => null]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_gbp_order_snapshots_its_own_live_mode_independently_of_eur_test_mode(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.gbp.test_mode' => false, 'corefy.gbp.callback_url' => 'https://game.example.test/api/v1/payments/corefy/gbp/webhook']);
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_gbp', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/gbp']]], 201)]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.payment_test_mode', false);

        Http::assertSent(fn (Request $request): bool => $request['data']['attributes']['test_mode'] === false);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_changing_currency_on_a_replayed_purchase_returns_422_without_a_second_invoice(): void
    {
        Sanctum::actingAs($this->player());
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_gbp', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/gbp']]], 201)]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk();
        $this->postJson('/api/v1/me/topup', [...$this->purchase(), 'currency' => 'EUR'])->assertUnprocessable();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['currency' => 'GBP', 'total_minor' => 200]);
    }

    public function test_policies_advertise_only_the_configured_checkout_currencies(): void
    {
        $this->get('/api/v1/legal/terms/page')->assertOk()->assertSee('Available checkout currencies: EUR, GBP.')
            ->assertDontSee('Only EUR is currently enabled')->assertDontSee('{payment.currencies}');
        $this->get('/api/v1/legal/refunds/page')->assertOk()->assertSee('Available checkout currencies: EUR, GBP.');
    }

    public function test_production_check_accepts_complete_live_profiles_without_printing_keys(): void
    {
        $this->configureProduction();

        $this->artisan('cdh:check-production')->expectsOutputToContain('PASS Corefy GBP API key is configured')
            ->doesntExpectOutputToContain('gbp-api-key')->doesntExpectOutputToContain('gbp-secret')->assertSuccessful();
    }

    public function test_production_check_fails_when_enabled_gbp_is_missing_its_api_key(): void
    {
        $this->configureProduction();
        config(['corefy.gbp.api_key' => '']);

        $this->artisan('cdh:check-production')->expectsOutputToContain('FAIL Corefy GBP API key is configured')->assertFailed();
    }

    public function test_production_check_does_not_require_credentials_for_disabled_gbp(): void
    {
        $this->configureProduction();
        config(['corefy.gbp.enabled' => false, 'corefy.gbp.api_key' => '']);

        $this->artisan('cdh:check-production')->assertSuccessful();
    }

    private function configureProduction(): void
    {
        config(['app.key' => str_repeat('a', 32), 'app.debug' => false, 'app.url' => 'https://game.example.test',
            'company.name' => 'Example Company', 'company.number' => '12345678', 'company.address' => '10 Example Street',
            'company.email' => 'info@example.test', 'mail.mailers.smtp.password' => 'test-mail-password',
            'queue.default' => 'database', 'cors.allowed_origins' => ['https://game.example.test'],
            'corefy.test_mode' => false, 'corefy.gbp.test_mode' => false]);
    }

    private function player(): User
    {
        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 10, 'soft_currency' => 0]);
        $user->address()->create(['street' => '10 Example Street', 'city' => 'London', 'country' => 'United Kingdom', 'postcode' => 'N22 8HH']);

        return $user;
    }

    private function pendingOrder(): Order
    {
        $user = $this->player();
        $order = $user->orders()->create(['public_id' => fake()->uuid(), 'status' => 'pending', 'provider' => 'corefy',
            'provider_payment_id' => 'cpi_gbp', 'idempotency_key' => fake()->uuid(), 'total_minor' => 200, 'currency' => 'GBP', 'payment_test_mode' => true]);
        $product = Product::create(['code' => 'test-gbp-bundle-'.fake()->uuid(), 'name' => 'Bundle', 'product_type' => 'diamonds', 'grant_quantity' => 120, 'price_minor' => 200, 'currency' => 'GBP']);
        foreach ([['diamonds', 120], ['soft_currency', 2400]] as [$type, $quantity]) {
            $order->items()->create(['product_id' => $product->id, 'product_code' => $product->code, 'product_name' => 'Test '.$type,
                'product_type' => $type, 'quantity' => 1, 'grant_quantity' => $quantity, 'unit_price_minor' => 100, 'currency' => 'GBP']);
        }

        return $order;
    }

    private function purchase(): array
    {
        return ['diamonds' => 120, 'gold' => 2400, 'currency' => 'GBP', 'idempotency_key' => 'gbp-test-purchase', 'accept_terms' => true, 'immediate_delivery' => true];
    }

    private function invoice(Order $order, array $overrides = []): array
    {
        return ['data' => ['id' => 'cpi_gbp', 'type' => 'payment-invoices', 'attributes' => [...[
            'reference_id' => $order->public_id, 'amount' => 2, 'currency' => 'GBP', 'test_mode' => true,
            'status' => 'processed', 'resolution' => 'ok', 'processed_amount' => 2,
        ], ...$overrides]]];
    }

    private function sendCallback(Order $order, string $endpoint = '/api/v1/payments/corefy/gbp/webhook', string $secret = 'gbp-secret'): TestResponse
    {
        $body = json_encode(['data' => ['id' => 'cpi_gbp', 'type' => 'payment-invoices', 'attributes' => ['reference_id' => $order->public_id]]], JSON_THROW_ON_ERROR);
        $signature = base64_encode(sha1($secret.$body.$secret, true));

        return $this->call('POST', $endpoint, [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SIGNATURE' => $signature], $body);
    }
}
