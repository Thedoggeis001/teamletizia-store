<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
    use AuthorizesRequests;

    public function createCheckoutSession(Order $order)
    {
        $this->authorize('update', $order);

        if ($order->status !== 'pending') {
            abort(409, 'Ordine non valido');
        }

        $order->load('items.product');

        if ($order->items->isEmpty()) {
            abort(400, 'Carrello vuoto');
        }

        try {
            $secret = config('services.stripe.secret');
            $successUrl = config('services.stripe.success_url');
            $cancelUrl = config('services.stripe.cancel_url');

            Log::info('STRIPE CHECKOUT START', [
                'order_id' => $order->id,
                'secret_present' => !empty($secret),
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'items_count' => $order->items->count(),
            ]);

            Stripe::setApiKey($secret);

            $lineItems = [];

            foreach ($order->items as $item) {
                $price = $item->product->base_price;

                $lineItems[] = [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => [
                            'name' => $item->product->name,
                        ],
                        'unit_amount' => (int) round($price * 100),
                    ],
                    'quantity' => $item->quantity,
                ];
            }

            Log::info('STRIPE LINE ITEMS READY', [
                'order_id' => $order->id,
                'line_items_count' => count($lineItems),
            ]);

            $session = Session::create([
                'mode' => 'payment',
                'line_items' => $lineItems,
                'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'order_id' => $order->id,
                ],
            ]);

            Log::info('STRIPE SESSION CREATED', [
                'order_id' => $order->id,
                'session_id' => $session->id ?? null,
                'session_url' => $session->url ?? null,
            ]);

            return response()->json([
                'url' => $session->url,
            ]);
        } catch (\Throwable $e) {
            Log::error('STRIPE CHECKOUT ERROR', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}