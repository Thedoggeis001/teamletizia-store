<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductKey;
use Illuminate\Http\Request;

class AdminProductKeyController extends Controller
{
    public function index(Product $product)
    {
        $keys = $product->keys()
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Keys fetched.',
            'errors' => null,
            'data' => $keys,
        ]);
    }

    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'key_value' => ['required', 'string', 'max:500'],
        ]);

        $key = $product->keys()->create([
            'key_value' => $validated['key_value'],
            'is_used' => false,
            'used_at' => null,
            'order_id' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Key created.',
            'errors' => null,
            'data' => $key,
        ], 201);
    }

    /**
     * POST /api/admin/products/{product}/keys/import
     * body: { "keys": ["AAA", "BBB", ...] }
     */
    public function import(Request $request, Product $product)
    {
        $validated = $request->validate([
            'keys' => ['required', 'array', 'min:1', 'max:5000'],
            'keys.*' => ['required', 'string', 'max:500'],
        ]);

        $values = collect($validated['keys'])
            ->map(fn ($k) => trim($k))
            ->filter()
            ->unique()
            ->values();

        $now = now();

        $insert = $values->map(fn ($k) => [
            'product_id' => $product->id,
            'order_id' => null,
            'key_value' => $k,
            'is_used' => false,
            'used_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        // Inserimento bulk (veloce)
        ProductKey::insert($insert);

        return response()->json([
            'success' => true,
            'message' => 'Keys imported.',
            'errors' => null,
            'data' => [
                'requested' => count($validated['keys']),
                'inserted' => count($insert),
            ],
        ], 201);
    }

    public function destroy(ProductKey $key)
    {
        // sicurezza: non cancellare se già usata/assegnata
        if ($key->is_used || $key->order_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Key cannot be deleted (already used/assigned).',
                'errors' => null,
                'data' => null,
            ], 409);
        }

        $key->delete();

        return response()->json([
            'success' => true,
            'message' => 'Key deleted.',
            'errors' => null,
            'data' => null,
        ]);
    }
}
