<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        if (! $endpointSecret) {
            return response()->json([
                'message' => 'Webhook secret non configurato',
            ], 500);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $endpointSecret
            );
        } catch (UnexpectedValueException $e) {
            return response()->json([
                'message' => 'Payload webhook non valido',
            ], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json([
                'message' => 'Firma webhook non valida',
            ], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;

                $orderId = $session->metadata->order_id ?? null;
                $paymentReference = $session->payment_intent ?? $session->id ?? null;

                if (! $orderId || ! $paymentReference) {
                    return response()->json([
                        'message' => 'Metadata ordine o payment reference mancanti',
                    ], 400);
                }

                DB::transaction(function () use ($orderId, $paymentReference) {
                    $order = Order::lockForUpdate()->find($orderId);

                    if (! $order) {
                        return;
                    }

                    if ($order->status === 'paid') {
                        return;
                    }

                    $order->checkout((string) $paymentReference);
                });

                break;
        }

        return response()->json(['received' => true], 200);
    }
}