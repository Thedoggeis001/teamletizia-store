<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'in:digital,physical'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $q = $validated['q'] ?? null;
        $type = $validated['type'] ?? null;
        $isActive = $validated['is_active'] ?? null;

        $products = Product::query()
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($isActive !== null, fn ($query) => $query->where('is_active', (bool) $isActive))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Admin products fetched.',
            'errors' => null,
            'data' => $products,
        ]);
    }

    public function show(Product $product)
    {
        $product->load('variants');

        return response()->json([
            'success' => true,
            'message' => 'Admin product fetched.',
            'errors' => null,
            'data' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'in:digital,physical'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'base_price' => $validated['base_price'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product created.',
            'errors' => null,
            'data' => $product,
        ], 201);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['sometimes', 'in:digital,physical'],
            'base_price' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product->fill($validated);
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Product updated.',
            'errors' => null,
            'data' => $product->fresh(),
        ]);
    }

    public function destroy(Product $product)
    {
        // soft delete? se non hai soft deletes, questa cancella
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted.',
            'errors' => null,
            'data' => null,
        ]);
    }
}
