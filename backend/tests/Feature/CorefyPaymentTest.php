<?php

namespace Tests\Feature;

use App\Mail\PurchaseReceipt;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CorefyPaymentService;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CorefyPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payments.provider' => 'corefy', 'corefy.account_id' => 'test-account', 'corefy.api_key' => 'test-api-key',
            'corefy.webhook_secret' => 'test-callback-key', 'corefy.test_mode' => true,
            'corefy.api_url' => 'https://api.sterling-pay.com', 'corefy.service_eur' => 'payment_card_eur_hpp',
            'corefy.hpp_hosts' => ['api.sterling-pay.com'], 'corefy.frontend_url' => 'https://game.example.test']);
        Http::preventStrayRequests();
    }

    public function test_checkout_creates_a_pending_eur_order_without_granting_goods_or_issuing_an_invoice(): void
    {
        Mail::fake();
        $user = $this->player();
        Sanctum::actingAs($user);
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_test', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/test']]], 201)]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.status', 'pending')->assertJsonPath('data.order.currency', 'EUR')
            ->assertJsonPath('data.order.total_minor', 200)->assertJsonPath('data.wallet.diamonds', 10)
            ->assertJsonPath('data.order.checkout_url', 'https://api.sterling-pay.com/hpp/test');

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Basic '.base64_encode('test-account:test-api-key'))
            && $request['data']['attributes']['amount'] === 2
            && $request['data']['attributes']['customer']['address']['country'] === 'GB'
            && $request['data']['attributes']['test_mode'] === true
            && str_ends_with($request['data']['attributes']['callback_url'], '/api/v1/payments/corefy/webhook'));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertNotNull(Order::firstOrFail()->digital_content_consent_at);
        Mail::assertNothingSent();
    }

    public function test_checkout_replay_reuses_the_same_provider_invoice(): void
    {
        Sanctum::actingAs($this->player());
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_test', 'attributes' => ['flow_data' => ['action' => 'https://api.sterling-pay.com/hpp/test', 'method' => 'GET']]]], 201)]);

        $first = $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk();
        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()->assertJsonPath('data.order.id', $first->json('data.order.id'));

        Http::assertSentCount(1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    #[DataProvider('flowMethods')]
    public function test_checkout_prefers_the_allowed_hpp_url_over_a_different_flow_action(string $method): void
    {
        Mail::fake();
        Sanctum::actingAs($this->player());
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_test', 'attributes' => [
                'hpp_url' => 'https://api.sterling-pay.com/hpp/test',
                'flow_data' => ['action' => 'https://checkout.s-pay.finance/pay/test', 'method' => $method],
            ]]], 201)]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.checkout_url', 'https://api.sterling-pay.com/hpp/test')
            ->assertJsonPath('data.order.status', 'pending')->assertJsonPath('data.wallet.diamonds', 10);

        Http::assertSentCount(1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
        Mail::assertNothingSent();
    }

    public static function flowMethods(): array
    {
        return ['GET' => ['GET'], 'POST' => ['POST']];
    }

    #[DataProvider('flowMethods')]
    public function test_an_unsafe_hpp_url_only_falls_back_to_an_allowed_get_flow(string $method): void
    {
        Sanctum::actingAs($this->player());
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::response([
            'data' => ['id' => 'cpi_test', 'attributes' => [
                'hpp_url' => 'https://attacker.example/pay',
                'flow_data' => ['action' => 'https://api.sterling-pay.com/hpp/flow', 'method' => $method],
            ]]], 201)]);

        $response = $this->postJson('/api/v1/me/topup', $this->purchase());
        if ($method === 'GET') {
            $response->assertOk()->assertJsonPath('data.order.checkout_url', 'https://api.sterling-pay.com/hpp/flow');
        } else {
            $response->assertServiceUnavailable();
            $this->assertDatabaseHas('orders', ['provider_payment_id' => 'cpi_test', 'checkout_url' => null]);
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_checking_a_pending_payment_restores_its_checkout_link_without_delivering_goods(): void
    {
        Mail::fake();
        $order = $this->pendingOrder();
        Sanctum::actingAs($order->user);
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response($this->invoice($order, [
            'status' => 'process_pending', 'processed_amount' => 0,
            'hpp_url' => 'https://api.sterling-pay.com/hpp/recovered',
            'flow_data' => ['action' => 'https://checkout.s-pay.finance/pay/test', 'method' => 'GET'],
        ]))]);

        $this->postJson('/api/v1/me/orders/'.$order->id.'/payment-status')->assertOk()
            ->assertJsonPath('data.order.status', 'pending')->assertJsonPath('data.order.provider_status', 'process_pending')
            ->assertJsonPath('data.order.checkout_url', 'https://api.sterling-pay.com/hpp/recovered')
            ->assertJsonPath('data.wallet.diamonds', 10);

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
        Mail::assertNothingSent();
    }

    public function test_checkout_retry_recovers_a_link_using_the_existing_invoice_without_another_provider_post(): void
    {
        Mail::fake();
        Sanctum::actingAs($this->player());
        Http::fake([
            'https://api.sterling-pay.com/payment-invoices' => Http::response([
                'data' => ['id' => 'cpi_test', 'attributes' => [
                    'flow_data' => ['action' => 'https://checkout.s-pay.finance/pay/test', 'method' => 'GET'],
                ]]], 201),
            'https://api.sterling-pay.com/payment-invoices/cpi_test' => fn (): PromiseInterface => Http::response(
                $this->invoice(Order::firstOrFail(), ['status' => 'process_pending', 'processed_amount' => 0,
                    'hpp_url' => 'https://api.sterling-pay.com/hpp/recovered'])
            ),
        ]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();
        $order = Order::firstOrFail();
        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.checkout_url', 'https://api.sterling-pay.com/hpp/recovered')
            ->assertJsonPath('data.order.status', 'pending')->assertJsonPath('data.wallet.diamonds', 10);

        Http::assertSentCount(2);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'POST'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
        Mail::assertNothingSent();
    }

    public function test_verified_callback_credits_goods_and_sends_one_pdf_receipt_even_when_replayed(): void
    {
        Storage::fake('local');
        Mail::fake();
        config(['company.name' => 'PROTECT YOUR ASSETS TODAY LTD', 'company.number' => '15969857',
            'company.address' => 'Academy House, 11 Dunraven Place, Bridgend, Mid Glamorgan, CF31 1JF']);
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response($this->invoice($order))]);

        $this->sendSignedWebhook($order)->assertOk();
        $this->sendSignedWebhook($order)->assertOk();

        $this->assertDatabaseHas('wallets', ['user_id' => $order->user_id, 'diamonds' => 110, 'soft_currency' => 2000]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid']);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payment_events', 1);
        Mail::assertSentCount(1);
        Mail::assertSent(PurchaseReceipt::class, function (PurchaseReceipt $mail): bool {
            $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($mail->invoice->pdf_path));
            $mail->assertSeeInHtml('TEST PAYMENT');

            return count($mail->attachments()) === 1;
        });
        if ($samplePath = getenv('COREFY_SAMPLE_PDF')) {
            File::ensureDirectoryExists(dirname($samplePath));
            File::put($samplePath, Storage::disk('local')->get($order->fresh()->invoice->pdf_path));
        }
    }

    public function test_invalid_or_tampered_signature_is_rejected_before_contacting_the_provider(): void
    {
        $order = $this->pendingOrder();

        $this->postJson('/api/v1/payments/corefy/webhook', ['data' => ['id' => 'cpi_test']], ['X-Signature' => 'invalid'])->assertUnauthorized();

        Http::assertNothingSent();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    #[DataProvider('unpaidStatuses')]
    public function test_non_successful_provider_status_does_not_credit_goods(string $status, string $expected): void
    {
        Mail::fake();
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response($this->invoice($order, ['status' => $status]))]);

        $this->sendSignedWebhook($order)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => $expected]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
        Mail::assertNothingSent();
    }

    public static function unpaidStatuses(): array
    {
        return ['pending' => ['process_pending', 'pending'], 'partial' => ['partially_processed', 'pending'],
            'failed' => ['process_failed', 'failed'], 'expired' => ['expired', 'failed'], 'terminated' => ['terminated', 'failed'],
            'refund' => ['refunded', 'refunded']];
    }

    #[DataProvider('mismatchedInvoices')]
    public function test_mismatched_private_provider_data_returns_422_and_does_not_credit(string $field, mixed $value): void
    {
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response($this->invoice($order, [$field => $value]))]);

        $this->sendSignedWebhook($order)->assertUnprocessable();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public static function mismatchedInvoices(): array
    {
        return ['amount' => ['amount', 3], 'currency' => ['currency', 'USD'], 'partial settlement' => ['processed_amount', 1.99],
            'missing settlement' => ['processed_amount', null], 'reference' => ['reference_id', '11111111-1111-4111-8111-111111111111'],
            'mode' => ['test_mode', false], 'missing mode' => ['test_mode', null], 'resolution' => ['resolution', 'declined']];
    }

    public function test_account_holder_can_reconcile_a_delayed_callback_but_another_account_cannot(): void
    {
        Storage::fake('local');
        Mail::fake();
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response($this->invoice($order))]);
        Sanctum::actingAs($this->player());

        $this->postJson('/api/v1/me/orders/'.$order->id.'/payment-status')->assertNotFound();
        Http::assertNothingSent();
        Sanctum::actingAs($order->user);
        $this->postJson('/api/v1/me/orders/'.$order->id.'/payment-status')->assertOk()
            ->assertJsonPath('data.order.status', 'paid')->assertJsonPath('data.wallet.diamonds', 110);

        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertSentCount(1);
    }

    public function test_checkout_requires_consent_and_only_eur_is_available(): void
    {
        Sanctum::actingAs($this->player());
        $payload = $this->purchase();
        unset($payload['accept_terms'], $payload['immediate_delivery']);

        $this->postJson('/api/v1/me/topup', $payload)->assertUnprocessable()->assertJsonValidationErrors(['accept_terms', 'immediate_delivery'], 'error.details');
        $this->postJson('/api/v1/me/topup', [...$this->purchase(), 'currency' => 'GBP'])->assertUnprocessable();
        $this->getJson('/api/v1/me/topup')->assertOk()->assertJsonPath('data.manual_enabled', false)
            ->assertJsonPath('data.payment_mode', 'corefy')->assertJsonCount(1, 'data.currencies');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_generic_and_sandbox_endpoints_cannot_bypass_corefy_verification(): void
    {
        $order = $this->pendingOrder();
        Sanctum::actingAs($order->user);

        $this->postJson('/api/v1/me/orders/'.$order->id.'/sandbox-pay')->assertNotFound();
        $this->postJson('/api/v1/payments/webhooks/test-provider', [
            'event_id' => 'bypass', 'type' => 'payment.succeeded', 'order_id' => $order->public_id,
            'payment_id' => 'fake', 'amount_minor' => 200, 'currency' => 'EUR',
        ])->assertNotFound();

        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_ambiguous_checkout_is_not_posted_twice(): void
    {
        Sanctum::actingAs($this->player());
        Http::fake(['https://api.sterling-pay.com/payment-invoices' => Http::failedConnection()]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();
        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();

        Http::assertSentCount(1);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('orders', ['provider_status' => 'creation_unknown']);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_refunded_order_cannot_be_recredited_by_an_old_processed_notification(): void
    {
        Storage::fake('local');
        Mail::fake();
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::sequence()
            ->push($this->invoice($order))->push($this->invoice($order, ['status' => 'refunded']))->push($this->invoice($order))]);

        $this->sendSignedWebhook($order)->assertOk();
        $this->sendSignedWebhook($order)->assertOk();
        $this->sendSignedWebhook($order)->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'refunded']);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertSentCount(1);
    }

    public function test_country_codes_cover_every_allowed_billing_country(): void
    {
        foreach (config('registration.allowed_countries') as $country) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/D', config('country_codes.'.$country));
        }
    }

    #[DataProvider('unsafeCheckoutUrls')]
    public function test_untrusted_checkout_links_are_rejected_without_creating_another_invoice(string $url): void
    {
        Sanctum::actingAs($this->player());
        Http::fake([
            'https://api.sterling-pay.com/payment-invoices' => Http::response([
                'data' => ['id' => 'cpi_test', 'attributes' => ['hpp_url' => $url]]], 201),
            'https://api.sterling-pay.com/payment-invoices/cpi_test' => fn (): PromiseInterface => Http::response(
                $this->invoice(Order::firstOrFail(), ['status' => 'process_pending', 'processed_amount' => 0, 'hpp_url' => $url])
            ),
        ]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();
        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();

        Http::assertSentCount(2);
        $this->assertCount(1, Http::recorded(fn (Request $request): bool => $request->method() === 'POST'));
        $this->assertDatabaseHas('orders', ['provider_payment_id' => 'cpi_test', 'checkout_url' => null]);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public static function unsafeCheckoutUrls(): array
    {
        return ['unrelated host' => ['https://attacker.example/pay'], 'insecure' => ['http://api.sterling-pay.com/hpp/test'],
            'credentials in URL' => ['https://attacker@api.sterling-pay.com/hpp/test'], 'script' => ['javascript:alert(1)']];
    }

    public function test_missing_credentials_fail_closed_without_creating_an_order(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.api_key' => '']);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_an_unsigned_callback_is_never_accepted_even_in_testing_mode(): void
    {
        $order = $this->pendingOrder();
        config(['corefy.webhook_secret' => '']);

        $this->sendSignedWebhook($order)->assertServiceUnavailable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_old_corefy_order_cannot_be_fulfilled_through_legacy_webhook_after_a_mode_switch(): void
    {
        $order = $this->pendingOrder();
        config(['payments.provider' => 'sandbox']);

        $this->postJson('/api/v1/payments/webhooks/test-provider', [
            'event_id' => 'bypass', 'type' => 'payment.succeeded', 'order_id' => $order->public_id,
            'payment_id' => 'fake', 'amount_minor' => 200, 'currency' => 'EUR',
        ])->assertNotFound();

        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_payment_status_requires_authentication(): void
    {
        $order = $this->pendingOrder();

        $this->postJson('/api/v1/me/orders/'.$order->id.'/payment-status')->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_reconciliation_command_verifies_before_delivering(): void
    {
        Storage::fake('local');
        Mail::fake();
        $order = $this->pendingOrder();
        $order->update(['provider_payment_id' => null]);
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response($this->invoice($order))]);

        $this->artisan('cdh:reconcile-payment', ['reference' => $order->public_id, '--invoice' => 'cpi_test'])->assertSuccessful();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid', 'provider_payment_id' => 'cpi_test']);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertSentCount(1);
    }

    public function test_corefy_policies_describe_eur_hosted_checkout_instead_of_manual_allocations(): void
    {
        $this->get('/api/v1/legal/terms/page')->assertOk()->assertSee('Only EUR is currently enabled')
            ->assertDontSee('The website currently operates in manual purchase mode');
        $this->get('/api/v1/legal/privacy/page')->assertOk()->assertSee('Sterling Pay / Corefy')->assertSee('UK GDPR');
        $this->get('/api/v1/legal/security/page')->assertOk()->assertSee('independently reads the provider invoice');
    }

    public function test_callback_arriving_before_checkout_response_does_not_lose_payment_or_credit_twice(): void
    {
        Storage::fake('local');
        Mail::fake();
        Sanctum::actingAs($this->player());
        Http::fake([
            'https://api.sterling-pay.com/payment-invoices' => function (): PromiseInterface {
                $order = Order::firstOrFail();
                $this->assertSame('creation_unknown', $order->provider_status);
                app(CorefyPaymentService::class)->synchronise($order, 'cpi_test');

                return Http::response(['data' => ['id' => 'cpi_test', 'attributes' => ['hpp_url' => 'https://api.sterling-pay.com/hpp/test']]], 201);
            },
            'https://api.sterling-pay.com/payment-invoices/cpi_test' => fn (): PromiseInterface => Http::response($this->invoice(Order::firstOrFail())),
        ]);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertOk()->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.wallet.diamonds', 110)->assertJsonPath('data.order.provider_status', 'processed');

        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('invoices', 1);
        Mail::assertSentCount(1);
    }

    public function test_live_mode_rejects_insecure_return_urls_before_creating_checkout(): void
    {
        Sanctum::actingAs($this->player());
        config(['corefy.test_mode' => false, 'corefy.frontend_url' => 'http://game.example.test']);

        $this->postJson('/api/v1/me/topup', $this->purchase())->assertServiceUnavailable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_unavailable_provider_keeps_the_order_unpaid(): void
    {
        $order = $this->pendingOrder();
        Http::fake(['https://api.sterling-pay.com/payment-invoices/cpi_test' => Http::response([], 503)]);

        $this->sendSignedWebhook($order)->assertStatus(502);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('invoices', 0);
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
            'provider_payment_id' => 'cpi_test', 'idempotency_key' => fake()->uuid(), 'total_minor' => 200, 'currency' => 'EUR', 'payment_test_mode' => true]);
        $product = Product::create(['code' => 'test-bundle-'.fake()->uuid(), 'name' => 'Bundle', 'product_type' => 'diamonds', 'grant_quantity' => 100, 'price_minor' => 200, 'currency' => 'EUR']);
        foreach ([['diamonds', 100], ['soft_currency', 2000]] as [$type, $quantity]) {
            $order->items()->create(['product_id' => $product->id, 'product_code' => $product->code, 'product_name' => 'Test '.$type,
                'product_type' => $type, 'quantity' => 1, 'grant_quantity' => $quantity, 'unit_price_minor' => 100, 'currency' => 'EUR']);
        }

        return $order;
    }

    private function purchase(): array
    {
        return ['diamonds' => 100, 'gold' => 2000, 'currency' => 'EUR', 'idempotency_key' => 'corefy-test-order-1', 'accept_terms' => true, 'immediate_delivery' => true];
    }

    private function invoice(Order $order, array $overrides = []): array
    {
        return ['data' => ['id' => 'cpi_test', 'type' => 'payment-invoices', 'attributes' => [...[
            'reference_id' => $order->public_id, 'amount' => 2, 'currency' => 'EUR', 'test_mode' => true,
            'status' => 'processed', 'resolution' => 'ok', 'processed_amount' => 2,
        ], ...$overrides]]];
    }

    private function sendSignedWebhook(Order $order): TestResponse
    {
        $body = json_encode(['data' => ['id' => 'cpi_test', 'type' => 'payment-invoices', 'attributes' => ['reference_id' => $order->public_id]]], JSON_THROW_ON_ERROR);
        $signature = base64_encode(sha1('test-callback-key'.$body.'test-callback-key', true));

        return $this->call('POST', '/api/v1/payments/corefy/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SIGNATURE' => $signature], $body);
    }
}
