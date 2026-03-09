<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CartController extends Controller
{
    use AuthorizesRequests;

    /**
     * Crea un nuovo carrello (Order pending)
     */
    public function create()
    {
        $order = Order::create([
            'user_id'      => auth()->id(),
            'total_amount' => 0,
            'status'       => 'pending',
        ]);

        return response()->json($order, 201);
    }

    /**
     * Mostra il carrello
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return response()->json(
            $order->load('items.product')
        );
    }

    /**
     * Aggiunge un prodotto al carrello
     */
    public function addItem(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity'   => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        $variant = isset($data['variant_id'])
            ? ProductVariant::find($data['variant_id'])
            : null;

        $order->addProduct(
            $product,
            $data['quantity'],
            $variant
        );

        return response()->json(
            $order->fresh()->load('items.product')
        );
    }

    /**
     * Rimuove una riga dal carrello
     */
    public function removeItem(Order $order, int $itemId)
    {
        $this->authorize('update', $order);

        $order->removeProductByItemId($itemId);

        return response()->json(
            $order->fresh()->load('items.product')
        );
    }

    /**
     * Checkout del carrello
     */
    public function checkout(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Ordine non modificabile'
            ], 409);
        }

        $data = $request->validate([
            'payment_reference' => ['required', 'string', 'max:255'],
        ]);

        return DB::transaction(function () use ($order, $data) {

            $order->load('items.product');

            if ($order->items->isEmpty()) {
                abort(400, 'Carrello vuoto');
            }

            $total = 0;

            foreach ($order->items as $item) {

                /**
                 * ✅ SNAPSHOT PREZZO
                 * Se unit_price è già valorizzato → lo rispettiamo
                 * Altrimenti congeliamo il prezzo attuale
                 */
                $price = $item->unit_price ?? $item->product->base_price;

                $item->update([
                    'unit_price' => $price,
                ]);

                $total += $price * $item->quantity;

                /**
                 * 🔐 ASSEGNAZIONE PRODUCT KEY (SOLO DIGITAL)
                 */
                if ($item->product->type === 'digital') {

                    $keys = ProductKey::where('product_id', $item->product_id)
                        ->whereNull('order_id')
                        ->lockForUpdate()
                        ->take($item->quantity)
                        ->get();

                    if ($keys->count() < $item->quantity) {
                        abort(409, 'Chiavi digitali insufficienti');
                    }

                    foreach ($keys as $key) {
                        $key->update([
                            'order_id' => $order->id,
                            'is_used'  => true,
                        ]);
                    }
                }
            }

            /**
             * ✅ FINALIZZAZIONE ORDINE
             */
            $order->update([
                'status'            => 'paid',
                'total_amount'      => $total,
                'payment_reference' => $data['payment_reference'],
                'discount_amount'   => 0,
            ]);

            return response()->json(
                $order->fresh()->load('items.product'),
                200
            );
        });
    }
}
