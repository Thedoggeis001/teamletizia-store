<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class AdminProductVariantController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'additional_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $variant = $product->variants()->create([
            'name' => $validated['name'],
            'price' => $validated['price'] ?? null,
            'additional_cost' => $validated['additional_cost'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Variant created.',
            'errors' => null,
            'data' => $variant,
        ], 201);
    }

    public function update(Request $request, ProductVariant $variant)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'additional_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $variant->fill($validated);
        $variant->save();

        return response()->json([
            'success' => true,
            'message' => 'Variant updated.',
            'errors' => null,
            'data' => $variant->fresh(),
        ]);
    }

    public function destroy(ProductVariant $variant)
    {
        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted.',
            'errors' => null,
            'data' => null,
        ]);
    }
}
