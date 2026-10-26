<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Services\CorefyPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CorefyPaymentController
{
    public function status(Request $request, Order $order, CorefyPaymentService $payments): JsonResponse
    {
        abort_unless($request->user()->id === $order->user_id, 404);
        if ($order->provider === 'corefy' && $order->provider_payment_id) {
            $order = $payments->synchronise($order);
        }

        return response()->json(['success' => true, 'data' => ['order' => $order->load(['items', 'invoice']), 'wallet' => $request->user()->wallet()->firstOrFail()]]);
    }

    public function webhook(Request $request, CorefyPaymentService $payments): JsonResponse
    {
        $payments->verifySignature($request->getContent(), (string) $request->header('X-Signature'));
        $data = $request->validate([
            'data.type' => ['required', 'in:payment-invoices'], 'data.id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,160}$/D'],
            'data.attributes.reference_id' => ['required', 'uuid'],
        ])['data'];
        $order = Order::query()->where('public_id', $data['attributes']['reference_id'])->where('provider', 'corefy')->firstOrFail();
        $payments->synchronise($order, $data['id']);

        return response()->json(['status' => 'ok', 'reference_id' => $order->public_id]);
    }
}
