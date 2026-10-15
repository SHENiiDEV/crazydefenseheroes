<?php

namespace Tests\Feature;

use App\Jobs\SendPurchaseReceipt;
use App\Mail\PurchaseReceipt;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CustomTopUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_purchase_credits_both_currencies_and_emails_pdf_invoice(): void
    {
        Storage::fake('local');
        Mail::fake();
        config(['topup.manual_enabled' => true, 'topup.diamonds_per_euro' => 100, 'topup.gold_per_euro' => 2000]);
        config(['company.name' => 'PROTECT YOUR ASSETS TODAY LTD', 'company.number' => '15969857',
            'company.address' => 'Academy House, 11 Dunraven Place, Bridgend, Mid Glamorgan, CF31 1JF']);
        $user = User::factory()->create(['email' => 'keeper@example.com', 'name' => 'Alex', 'surname' => 'Keeper']);
        $user->address()->create(['street' => '12 Example Street', 'city' => 'Riga', 'country' => 'Latvia', 'postcode' => 'LV-1010']);
        $user->wallet()->create(['diamonds' => 12, 'soft_currency' => 75]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/me/topup', [
            'diamonds' => 20400, 'gold' => 120000, 'idempotency_key' => 'custom-both-0001',
        ])->assertOk()
            ->assertJsonPath('data.order.status', 'paid')
            ->assertJsonPath('data.order.provider', 'manual')
            ->assertJsonPath('data.order.total_minor', 26400)
            ->assertJsonPath('data.wallet.diamonds', 20412)
            ->assertJsonPath('data.wallet.soft_currency', 120075)
            ->assertJsonCount(2, 'data.order.items');

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($invoice->id, $response->json('data.order.invoice.id'));
        $this->assertNotNull($invoice->sent_at);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $pdf = Storage::disk('local')->get($invoice->pdf_path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        Mail::assertSent(PurchaseReceipt::class, function (PurchaseReceipt $mail) use ($invoice, $pdf): bool {
            $mail->assertTo('keeper@example.com');
            $mail->assertHasAttachedData($pdf, 'invoice-'.$invoice->invoice_number.'.pdf', ['mime' => 'application/pdf']);
            $mail->assertSeeInHtml('264.00 EUR');
            $mail->assertSeeInHtml('No money was collected');

            return true;
        });
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $user->id, 'diamonds_delta' => 20400, 'soft_currency_delta' => 120000]);

        if ($samplePath = getenv('TOPUP_SAMPLE_PDF')) {
            File::ensureDirectoryExists(dirname($samplePath));
            File::put($samplePath, $pdf);
        }
    }

    public function test_retrying_purchase_does_not_duplicate_currency_invoice_or_email(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = User::factory()->create();
        $user->wallet()->create(['diamonds' => 0, 'soft_currency' => 0]);
        Sanctum::actingAs($user);
        $payload = ['diamonds' => 333, 'gold' => 1001, 'idempotency_key' => 'custom-retry-0001'];

        $first = $this->postJson('/api/v1/me/topup', $payload)->assertOk();
        $this->postJson('/api/v1/me/topup', $payload)->assertOk()
            ->assertJsonPath('data.order.id', $first->json('data.order.id'))
            ->assertJsonPath('data.wallet.diamonds', 333)
            ->assertJsonPath('data.wallet.soft_currency', 1001);

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertSentCount(1);
    }

    public function test_reusing_reference_for_different_amount_returns_422_without_new_grant(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/topup', ['diamonds' => 500, 'gold' => 0, 'idempotency_key' => 'same-reference-1'])->assertOk();

        $this->postJson('/api/v1/me/topup', ['diamonds' => 900, 'gold' => 0, 'idempotency_key' => 'same-reference-1'])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'diamonds' => 500]);
        Mail::assertSentCount(1);
    }

    #[DataProvider('singleCurrencyPurchases')]
    public function test_single_currency_amounts_round_up_to_a_cent(int $diamonds, int $gold, int $total): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/topup', ['diamonds' => $diamonds, 'gold' => $gold, 'idempotency_key' => 'single-currency-1'])
            ->assertOk()->assertJsonPath('data.order.total_minor', $total)->assertJsonCount(1, 'data.order.items');

        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'diamonds' => $diamonds, 'soft_currency' => $gold]);
        Mail::assertSentCount(1);
    }

    public static function singleCurrencyPurchases(): array
    {
        return ['diamonds only' => [20400, 0, 20400], 'gold only' => [0, 120000, 6000], 'one gold coin' => [0, 1, 1]];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_return_422_without_creating_orders(mixed $diamonds, mixed $gold): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/topup', ['diamonds' => $diamonds, 'gold' => $gold, 'idempotency_key' => 'invalid-amount-1'])
            ->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public static function invalidAmounts(): array
    {
        return ['empty' => [0, 0], 'negative' => [-1, 5], 'fraction' => [2.5, 1], 'too large' => [100000001, 1], 'missing' => [null, null]];
    }

    public function test_purchase_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/topup', ['diamonds' => 1, 'gold' => 1, 'idempotency_key' => 'unauthenticated-1'])->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_disabling_manual_purchases_returns_403_and_grants_nothing(): void
    {
        config(['topup.manual_enabled' => false]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/topup', ['diamonds' => 100, 'gold' => 100, 'idempotency_key' => 'disabled-topup-1'])->assertForbidden();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_receipt_is_queued_and_sent_once_when_worker_runs(): void
    {
        Storage::fake('local');
        Queue::fake([SendPurchaseReceipt::class]);
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/topup', ['diamonds' => 500, 'gold' => 0, 'idempotency_key' => 'queued-receipt-1'])->assertOk();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertNull($invoice->sent_at);
        Queue::assertPushed(SendPurchaseReceipt::class, fn (SendPurchaseReceipt $job): bool => $job->invoiceId === $invoice->id);
        Mail::assertNothingSent();
        $job = new SendPurchaseReceipt($invoice->id);
        $job->handle();
        $job->handle();
        Mail::assertSentCount(1);
        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_failed_email_remains_unsent_and_can_be_retried(): void
    {
        Storage::fake('local');
        Queue::fake([SendPurchaseReceipt::class]);
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/me/topup', ['diamonds' => 500, 'gold' => 0, 'idempotency_key' => 'failed-receipt-1'])->assertOk();
        $invoice = Invoice::query()->firstOrFail();
        Mail::shouldReceive('to')->once()->with($user->email)->andThrow(new RuntimeException('SMTP unavailable'));

        try {
            (new SendPurchaseReceipt($invoice->id))->handle();
            $this->fail('The queue must see the SMTP failure so it can retry.');
        } catch (RuntimeException $exception) {
            $this->assertSame('SMTP unavailable', $exception->getMessage());
        }

        $this->assertNull($invoice->fresh()->sent_at);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        (new SendPurchaseReceipt($invoice->id))->handle();
        Mail::assertSent(PurchaseReceipt::class);
        $this->assertNotNull($invoice->fresh()->sent_at);
    }

    public function test_receipt_transport_contains_the_real_pdf_attachment(): void
    {
        Storage::fake('local');
        config(['mail.default' => 'array']);
        $user = User::factory()->create(['email' => 'keeper@example.com']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/me/topup', ['diamonds' => 20400, 'gold' => 120000, 'idempotency_key' => 'mime-receipt-0001'])->assertOk();

        $invoice = Invoice::query()->firstOrFail();
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $message = $messages->first()->getOriginalMessage();
        $this->assertSame('keeper@example.com', $message->getTo()[0]->getAddress());
        $this->assertCount(1, $message->getAttachments());
        $attachment = $message->getAttachments()[0];
        $this->assertSame('invoice-'.$invoice->invoice_number.'.pdf', $attachment->getFilename());
        $this->assertSame('application', $attachment->getMediaType());
        $this->assertSame('pdf', $attachment->getMediaSubtype());
        $this->assertSame(Storage::disk('local')->get($invoice->pdf_path), $attachment->getBody());
        $this->assertNotNull($invoice->sent_at);
    }

    public function test_history_includes_invoice_and_other_players_cannot_download_it(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $purchase = $this->postJson('/api/v1/me/topup', ['diamonds' => 100, 'gold' => 100, 'idempotency_key' => 'history-purchase-1'])->assertOk();
        $invoiceId = $purchase->json('data.order.invoice.id');

        $this->getJson('/api/v1/me/orders')->assertOk()->assertJsonPath('data.orders.0.invoice.id', $invoiceId);
        $this->get('/api/v1/me/invoices/'.$invoiceId)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        Sanctum::actingAs(User::factory()->create());
        $this->get('/api/v1/me/invoices/'.$invoiceId)->assertNotFound();
        Mail::assertSentCount(1);
    }

    public function test_legacy_direct_credit_endpoint_is_retired(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/me/wallet/topup', ['package_code' => 'free-demo-100'])->assertGone();

        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
