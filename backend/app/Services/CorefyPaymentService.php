<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

final class CorefyPaymentService
{
    public function assertConfigured(): void
    {
        foreach (['account_id', 'api_key', 'webhook_secret', 'service_eur'] as $key) {
            abort_if(blank(config('corefy.'.$key)), 503, 'Sterling Pay checkout is not configured. Please contact support.');
        }
        abort_unless(parse_url(config('corefy.api_url'), PHP_URL_SCHEME) === 'https', 503, 'The payment API must use HTTPS.');
        if (! config('corefy.test_mode')) {
            $callback = config('corefy.callback_url') ?: route('api.v1.corefy.webhook');
            abort_unless(parse_url(config('corefy.frontend_url'), PHP_URL_SCHEME) === 'https'
                && parse_url($callback, PHP_URL_SCHEME) === 'https', 503, 'Live checkout requires HTTPS return and callback URLs.');
        }
    }

    public function checkout(Order $order): Order
    {
        $this->assertConfigured();
        [$order, $create] = DB::transaction(function () use ($order): array {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($order->provider === 'corefy' && $order->currency === 'EUR', 422, 'This order cannot use Sterling Pay.');
            abort_unless($order->payment_test_mode === (bool) config('corefy.test_mode'), 422, 'This order belongs to a different payment mode.');
            if ($order->checkout_url || $order->status !== 'pending' || $order->provider_status === 'creation_unknown' || $order->provider_payment_id) {
                return [$order, false];
            }
            $order->load('user.address');
            abort_unless($order->user->address && config('country_codes.'.$order->user->address->country), 422, 'Complete your billing address before paying.');
            // Commit the attempt before the external POST: a crash or timeout must not trigger a second checkout.
            $order->update(['provider_status' => 'creation_unknown']);

            return [$order, true];
        });
        if ($create) {
            $order = $this->createInvoice($order);
        } elseif ($order->status === 'pending' && ! $order->checkout_url && $order->provider_payment_id) {
            $order = $this->synchronise($order);
        }
        abort_if(! $order->checkout_url && $order->status !== 'paid', 503, 'Checkout could not be confirmed. Your items have not been credited. Check this order or contact support before trying a new purchase.');

        return $order->load(['items', 'invoice']);
    }

    private function createInvoice(Order $order): Order
    {
        $user = $order->user;
        $address = $user->address;
        $returnUrl = config('corefy.frontend_url').'/?payment_return='.rawurlencode($order->public_id);
        $attributes = [
            'reference_id' => $order->public_id, 'amount' => $order->total_minor / 100, 'currency' => $order->currency,
            'service' => config('corefy.service_eur'), 'flow' => 'charge', 'test_mode' => $order->payment_test_mode,
            'description' => 'Crazy Defense Heroes digital goods - '.$order->public_id,
            'customer' => [
                'reference_id' => 'player_'.$user->id, 'email' => $user->email, 'name' => $user->name.' '.$user->surname,
                'first_name' => $user->name, 'surname' => $user->surname, 'phone' => $user->phone, 'date_of_birth' => $user->date_of_birth,
                'address' => ['full_address' => $address->street.', '.$address->city.', '.$address->postcode,
                    'country' => config('country_codes.'.$address->country), 'city' => $address->city, 'street' => $address->street, 'post_code' => $address->postcode],
            ],
            'return_url' => $returnUrl, 'return_urls' => ['success' => $returnUrl, 'pending' => $returnUrl, 'fail' => $returnUrl],
            'callback_url' => config('corefy.callback_url') ?: route('api.v1.corefy.webhook'),
            'metadata' => ['source' => 'crazy-defense-heroes', 'transaction_ref' => $order->public_id],
        ];
        try {
            $response = $this->client()->post('/payment-invoices', ['data' => ['type' => 'payment-invoices', 'attributes' => $attributes]]);
        } catch (ConnectionException) {
            return $order;
        }
        if ($response->status() !== 201) {
            return $order;
        }
        $data = $response->json('data');
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! preg_match('/^[A-Za-z0-9_-]{1,160}$/D', $data['id'])) {
            return $order;
        }

        return DB::transaction(function () use ($order, $data): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($order->provider_payment_id && $order->provider_payment_id !== $data['id'], 422, 'A different provider invoice is already linked.');
            $order->update(['provider_payment_id' => $data['id']]);
            if ($order->status !== 'pending') {
                return $order;
            }
            $attributes = $data['attributes'] ?? [];
            if (! is_array($attributes)) {
                return $order;
            }
            $matches = (! isset($attributes['reference_id']) || $attributes['reference_id'] === $order->public_id)
                && (! isset($attributes['currency']) || $attributes['currency'] === $order->currency)
                && (! isset($attributes['amount']) || $this->minorAmount($attributes['amount']) === $order->total_minor)
                && (! isset($attributes['test_mode']) || $attributes['test_mode'] === $order->payment_test_mode);
            $url = $this->checkoutUrl($attributes);
            if ($matches && $url !== null) {
                $order->update(['checkout_url' => $url, 'provider_status' => 'created']);
            }

            return $order;
        });
    }

    public function synchronise(Order $order, ?string $invoiceId = null): Order
    {
        $this->assertConfigured();
        $id = $invoiceId ?? $order->provider_payment_id;
        abort_unless($order->provider === 'corefy' && is_string($id) && preg_match('/^[A-Za-z0-9_-]{1,160}$/D', $id), 422, 'The payment invoice is not yet available. Contact support with the order reference.');
        $response = $this->client()->get('/payment-invoices/'.rawurlencode($id));
        abort_unless($response->successful(), 502, 'The payment provider could not confirm the status. Please retry later.');
        $data = $response->json('data');
        abort_unless(is_array($data) && ($data['id'] ?? null) === $id && ($data['type'] ?? null) === 'payment-invoices', 422, 'The payment invoice does not match.');
        $attributes = $data['attributes'] ?? [];
        $this->assertMatchesOrder($order, $attributes);

        return DB::transaction(function () use ($order, $attributes, $id): Order {
            $order = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_if($order->provider_payment_id && $order->provider_payment_id !== $id, 422, 'A different payment invoice is already linked.');
            $status = (string) ($attributes['status'] ?? 'unknown');
            $refundRecorded = in_array($order->status, ['refunded', 'partially_refunded', 'refund_review'], true);
            $refundFinalised = $order->status !== 'refunded' && in_array($status, ['refunded', 'partially_refunded'], true);
            if (! $refundRecorded || $refundFinalised) {
                $order->update(['provider_payment_id' => $id, 'provider_status' => $status]);
                if ($status === 'processed') {
                    abort_unless(($attributes['resolution'] ?? '') === 'ok' && $this->minorAmount($attributes['processed_amount'] ?? null) === $order->total_minor, 422, 'The settled payment amount does not match the order.');
                    app(PaymentService::class)->markPaid($order, 'corefy', $id);
                } elseif (in_array($status, ['refunded', 'partially_refunded', 'refunding', 'refund_pending'], true)) {
                    // Consumed items cannot safely be clawed back automatically; support reviews the account.
                    $order->update(['status' => match ($status) {
                        'refunded' => 'refunded', 'partially_refunded' => 'partially_refunded', default => 'refund_review'
                    }, 'checkout_url' => null]);
                } elseif ($order->status !== 'paid' && in_array($status, ['process_failed', 'expired', 'terminated'], true)) {
                    $order->update(['status' => 'failed', 'checkout_url' => null]);
                } elseif ($order->status === 'pending' && in_array($status, ['created', 'pending', 'processing', 'process_pending'], true)) {
                    $url = $this->checkoutUrl($attributes);
                    if ($url !== null) {
                        $order->update(['checkout_url' => $url]);
                    }
                }
            }
            $eventId = hash('sha256', $id.':'.$status.':'.($attributes['updated'] ?? '').':'.($attributes['processed_amount'] ?? ''));
            PaymentEvent::firstOrCreate(['provider' => 'corefy', 'event_id' => $eventId], [
                'event_type' => $status, 'processed_at' => now(),
                'payload' => ['invoice_id' => $id, 'reference_id' => $order->public_id, 'status' => $status, 'currency' => $order->currency],
            ]);

            return $order->fresh()->load(['items', 'invoice']);
        });
    }

    public function verifySignature(string $body, string $signature): void
    {
        $secret = (string) config('corefy.webhook_secret');
        abort_if($secret === '', 503, 'The payment callback is not configured.');
        $expected = base64_encode(sha1($secret.$body.$secret, true));
        abort_unless($signature !== '' && hash_equals($expected, $signature), 401, 'Invalid payment signature.');
    }

    private function assertMatchesOrder(Order $order, array $attributes): void
    {
        abort_unless(($attributes['reference_id'] ?? null) === $order->public_id
            && ($attributes['currency'] ?? null) === $order->currency
            && $this->minorAmount($attributes['amount'] ?? null) === $order->total_minor
            && is_bool($attributes['test_mode'] ?? null)
            && $attributes['test_mode'] === $order->payment_test_mode
            && $order->payment_test_mode === (bool) config('corefy.test_mode'), 422, 'Payment reference, amount, currency or mode does not match the order.');
    }

    private function minorAmount(mixed $amount): ?int
    {
        if ((! is_int($amount) && ! is_float($amount) && ! is_string($amount)) || ! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', (string) $amount, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    private function isAllowedCheckoutUrl(mixed $url): bool
    {
        return is_string($url) && parse_url($url, PHP_URL_SCHEME) === 'https'
            && ! parse_url($url, PHP_URL_USER) && ! parse_url($url, PHP_URL_PASS)
            && in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), config('corefy.hpp_hosts'), true)
            && in_array(parse_url($url, PHP_URL_PORT), [null, 443], true);
    }

    private function checkoutUrl(array $attributes): ?string
    {
        $hppUrl = $attributes['hpp_url'] ?? null;
        if ($this->isAllowedCheckoutUrl($hppUrl)) {
            return $hppUrl;
        }
        $flow = $attributes['flow_data'] ?? null;
        if (! is_array($flow)) {
            return null;
        }
        $method = $flow['method'] ?? 'GET';
        $action = $flow['action'] ?? null;

        return is_string($method) && strtoupper($method) === 'GET' && $this->isAllowedCheckoutUrl($action)
            ? $action : null;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(config('corefy.api_url'))
            ->withBasicAuth(config('corefy.account_id'), config('corefy.api_key'))
            ->accept('application/vnd.api+json')->contentType('application/vnd.api+json')
            ->connectTimeout(10)->timeout(25)->withOptions(['allow_redirects' => false]);
    }
}
