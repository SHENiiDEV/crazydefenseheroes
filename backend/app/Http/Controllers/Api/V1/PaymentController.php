<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PaymentWebhookRequest;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PaymentController
{
    public function sandboxPay(Request $request, Order $order, PaymentService $payments): JsonResponse
    {
        abort_unless(app()->environment(['local', 'testing']), 404);
        $this->assertOwner($request, $order);

        $paidOrder = $payments->markPaid($order, 'sandbox', 'sandbox-'.$order->public_id);

        return response()->json([
            'success' => true,
            'data' => ['order' => $paidOrder],
        ]);
    }

    public function webhook(PaymentWebhookRequest $request, string $provider, PaymentService $payments): JsonResponse
    {
        $this->verifySignature($request);
        $data = $request->validated();

        $existingEvent = PaymentEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $data['event_id'])
            ->first();

        if ($existingEvent) {
            return $this->webhookResponse($existingEvent->event_type, true);
        }

        $event = DB::transaction(function () use ($data, $provider, $payments): PaymentEvent {
            $order = Order::query()
                ->where('public_id', $data['order_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->total_minor !== $data['amount_minor'] || $order->currency !== strtoupper($data['currency'])) {
                throw ValidationException::withMessages([
                    'amount_minor' => ['The payment amount does not match the order.'],
                ]);
            }

            $event = PaymentEvent::create([
                'provider' => $provider,
                'event_id' => $data['event_id'],
                'event_type' => $data['type'],
                'payload' => $data,
                'processed_at' => now(),
            ]);

            if ($order->status !== 'paid') {
                $payments->markPaid($order, $provider, $data['payment_id']);
            }

            return $event;
        });

        return $this->webhookResponse($event->event_type, false);
    }

    private function verifySignature(PaymentWebhookRequest $request): void
    {
        $secret = (string) config('payments.webhook_secret');

        if ($secret === '') {
            abort_unless(! app()->environment('production'), 500, 'Payment webhook secret is not configured.');

            return;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        $provided = (string) $request->header('X-Payment-Signature');

        abort_unless($provided !== '' && hash_equals($expected, $provided), 401, 'Invalid payment webhook signature.');
    }

    private function assertOwner(Request $request, Order $order): void
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($order->user_id === $user->id, 404);
    }

    private function webhookResponse(string $eventType, bool $idempotent): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'event_type' => $eventType,
                'idempotent' => $idempotent,
            ],
        ]);
    }
}
