<?php

namespace Tests\Feature\Api;

use App\Mail\PurchaseReceipt;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_creates_pdf_invoice_and_sends_receipt(): void
    {
        Storage::fake('local');
        Mail::fake();

        $product = Product::create([
            'code' => 'diamonds-500',
            'name' => '500 Diamonds',
            'product_type' => 'diamonds',
            'grant_quantity' => 500,
            'price_minor' => 499,
            'currency' => 'EUR',
            'active' => true,
        ]);
        $user = User::factory()->create(['email' => 'keeper@example.com']);
        $user->wallet()->create();
        Sanctum::actingAs($user);

        $order = $this->postJson('/api/v1/me/orders', [
            'product_code' => $product->code,
            'quantity' => 1,
            'idempotency_key' => 'invoice-order-0001',
        ])->json('data.order');

        $this->postJson('/api/v1/me/orders/'.$order['id'].'/sandbox-pay')
            ->assertOk();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertMatchesRegularExpression('/^CDH-\d{4}-[A-Z0-9]{16}$/', $invoice->invoice_number);
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->get('/api/v1/me/invoices/'.$invoice->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        Mail::assertSent(PurchaseReceipt::class, fn (PurchaseReceipt $mail): bool => (
            $mail->invoice->invoice_number === $invoice->invoice_number
        ));
        $this->assertStringStartsWith('%PDF-', (string) Storage::disk('local')->get($invoice->pdf_path));
    }

    public function test_random_invoice_number_collision_generates_a_new_number(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->travelTo('2026-10-15 12:00:00');
        config(['invoice.number_prefix' => 'CDH-']);
        $user = User::factory()->create();
        $firstOrder = $user->orders()->create([
            'public_id' => (string) Str::uuid(), 'status' => 'paid', 'total_minor' => 100,
            'currency' => 'EUR', 'idempotency_key' => 'invoice-random-1',
        ]);
        Invoice::create([
            'order_id' => $firstOrder->id, 'user_id' => $user->id, 'invoice_number' => 'CDH-2026-AAAAAAAAAAAAAAAA',
            'subtotal_minor' => 100, 'tax_minor' => 0, 'total_minor' => 100, 'currency' => 'EUR', 'issued_at' => now(),
        ]);
        $secondOrder = $user->orders()->create([
            'public_id' => (string) Str::uuid(), 'status' => 'paid', 'total_minor' => 100,
            'currency' => 'EUR', 'idempotency_key' => 'invoice-random-2',
        ]);
        $suffixes = [str_repeat('a', 16), str_repeat('b', 16)];
        Str::createRandomStringsUsing(function (int $length) use (&$suffixes): string {
            return $length === 16 ? (array_shift($suffixes) ?? str_repeat('x', $length)) : str_repeat('x', $length);
        });

        try {
            $invoice = app(InvoiceService::class)->issueForOrder($secondOrder);
            $this->assertSame('CDH-2026-BBBBBBBBBBBBBBBB', $invoice->invoice_number);
        } finally {
            Str::createRandomStringsNormally();
        }

        $this->assertDatabaseCount('invoices', 2);
        Mail::assertSentCount(1);
    }

    public function test_reissuing_existing_sequential_invoice_preserves_its_number(): void
    {
        Storage::fake('local');
        Mail::fake();
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'public_id' => (string) Str::uuid(), 'status' => 'paid', 'total_minor' => 100,
            'currency' => 'EUR', 'idempotency_key' => 'existing-invoice-1',
        ]);
        $invoice = Invoice::create([
            'order_id' => $order->id, 'user_id' => $user->id, 'invoice_number' => 'CDH-2026-000001',
            'subtotal_minor' => 100, 'tax_minor' => 0, 'total_minor' => 100, 'currency' => 'EUR', 'issued_at' => now(),
        ]);

        $issued = app(InvoiceService::class)->issueForOrder($order);
        app(InvoiceService::class)->issueForOrder($order);

        $this->assertSame($invoice->id, $issued->id);
        $this->assertSame('CDH-2026-000001', $issued->invoice_number);
        Storage::disk('local')->assertExists('invoices/CDH-2026-000001.pdf');
        $this->assertDatabaseCount('invoices', 1);
        Mail::assertSentCount(1);
    }
}
