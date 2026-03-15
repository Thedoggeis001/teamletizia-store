<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{
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

        Stripe::setApiKey(config('services.stripe.secret'));

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

        $session = Session::create([
            'mode' => 'payment',
            'line_items' => $lineItems,
            'success_url' => config('services.stripe.success_url') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('services.stripe.cancel_url'),
            'metadata' => [
                'order_id' => $order->id,
            ],
        ]);

        return response()->json([
            'url' => $session->url,
        ]);
    }
}