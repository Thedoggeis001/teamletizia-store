<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OrderController extends Controller
{
    use AuthorizesRequests;

    /**
     * Lista ordini dell'utente loggato
     */
    public function index()
    {
        $orders = Order::where('user_id', auth()->id())
            ->with(['items.product', 'items.variant'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($orders);
    }

    /**
     * Dettaglio singolo ordine
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return response()->json(
            $order->load(['items.product', 'items.variant'])
        );
    }

    /**
     * Chiavi associate a un ordine
     */
    public function keys(Order $order)
    {
        $this->authorize('view', $order);

        $keys = $order->productKeys()
            ->with('product:id,name')
            ->get([
                'id',
                'product_id',
                'order_id',
                'key_value',
                'is_used',
                'used_at',
                'created_at',
            ]);

        return response()->json($keys);
    }
}