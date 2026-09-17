<?php

namespace Tests\Feature\Api;

use App\Mail\PurchaseReceipt;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        Storage::disk('local')->assertExists($invoice->pdf_path);
        $this->get('/api/v1/me/invoices/'.$invoice->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        Mail::assertSent(PurchaseReceipt::class, fn (PurchaseReceipt $mail): bool => (
            $mail->invoice->invoice_number === $invoice->invoice_number
        ));
        $this->assertStringStartsWith('%PDF-', (string) Storage::disk('local')->get($invoice->pdf_path));
    }
}
