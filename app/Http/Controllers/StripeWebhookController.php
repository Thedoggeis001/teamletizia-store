<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $stripeSecret = config('services.stripe.secret');

        if (! $endpointSecret) {
            Log::error('STRIPE WEBHOOK ERROR: webhook secret non configurato');

            return response()->json([
                'message' => 'Webhook secret non configurato',
            ], 500);
        }

        if (! $stripeSecret) {
            Log::error('STRIPE WEBHOOK ERROR: stripe secret non configurato');

            return response()->json([
                'message' => 'Stripe secret non configurato',
            ], 500);
        }

        try {
            Stripe::setApiKey($stripeSecret);

            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $endpointSecret
            );
        } catch (UnexpectedValueException $e) {
            Log::warning('STRIPE WEBHOOK ERROR: payload non valido', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Payload webhook non valido',
            ], 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('STRIPE WEBHOOK ERROR: firma non valida', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Firma webhook non valida',
            ], 400);
        } catch (\Throwable $e) {
            Log::error('STRIPE WEBHOOK ERROR: eccezione durante constructEvent', [
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            return response()->json([
                'message' => 'Errore interno webhook',
            ], 500);
        }

        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $session = $event->data->object;

                    $orderId = $session->metadata->order_id ?? null;
                    $paymentReference = $session->payment_intent ?? $session->id ?? null;

                    Log::info('STRIPE WEBHOOK SESSION COMPLETED', [
                        'event_id' => $event->id ?? null,
                        'order_id' => $orderId,
                        'payment_reference' => $paymentReference,
                        'session_id' => $session->id ?? null,
                        'payment_status' => $session->payment_status ?? null,
                        'amount_total' => $session->amount_total ?? null,
                        'customer_email' => $session->customer_details->email ?? null,
                    ]);

                    if (! $orderId || ! $paymentReference) {
                        Log::warning('STRIPE WEBHOOK ERROR: metadata ordine o payment reference mancanti', [
                            'event_id' => $event->id ?? null,
                            'order_id' => $orderId,
                            'payment_reference' => $paymentReference,
                        ]);

                        return response()->json([
                            'message' => 'Metadata ordine o payment reference mancanti',
                        ], 400);
                    }

                    DB::transaction(function () use ($orderId, $paymentReference) {
                        $order = Order::lockForUpdate()->find($orderId);

                        Log::info('STRIPE WEBHOOK ORDER LOOKUP', [
                            'order_id' => $orderId,
                            'order_found' => (bool) $order,
                        ]);

                        if (! $order) {
                            throw new \RuntimeException("Ordine {$orderId} non trovato nel webhook");
                        }

                        if ($order->status === 'paid') {
                            Log::info('STRIPE WEBHOOK ORDER ALREADY PAID', [
                                'order_id' => $order->id,
                                'status' => $order->status,
                            ]);

                            return;
                        }

                        $order->checkout((string) $paymentReference);

                        $order->refresh();

                        Log::info('STRIPE WEBHOOK ORDER UPDATED', [
                            'order_id' => $order->id,
                            'status' => $order->status,
                            'total_amount' => $order->total_amount,
                            'payment_reference' => $order->payment_reference,
                        ]);
                    });

                    break;

                default:
                    Log::info('STRIPE WEBHOOK EVENT IGNORED', [
                        'event_type' => $event->type,
                        'event_id' => $event->id ?? null,
                    ]);

                    break;
            }
        } catch (\Throwable $e) {
            Log::error('STRIPE WEBHOOK PROCESSING ERROR', [
                'event_type' => $event->type ?? null,
                'event_id' => $event->id ?? null,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json(['received' => true], 200);
    }
}