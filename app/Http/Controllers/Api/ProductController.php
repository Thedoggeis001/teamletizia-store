<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * GET /api/products
     * Public list (frontend-ready)
     * - pagination: ?page= & ?per_page=
     * - search: ?q=
     * - filter: ?type=digital|physical
     * - sort: ?sort=name|base_price|created_at & ?dir=asc|desc
     * - only is_active = true
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'q' => ['sometimes', 'string', 'max:200'],
            'type' => ['sometimes', 'in:digital,physical'],
            'sort' => ['sometimes', 'in:name,base_price,created_at'],
            'dir' => ['sometimes', 'in:asc,desc'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 12);
        $q = $validated['q'] ?? null;
        $type = $validated['type'] ?? null;

        $sort = $validated['sort'] ?? 'created_at';
        $dir  = $validated['dir'] ?? 'desc';

        // whitelist anti SQL injection
        $sortMap = [
            'name' => 'name',
            'base_price' => 'base_price',
            'created_at' => 'created_at',
        ];
        $sortColumn = $sortMap[$sort] ?? 'created_at';

        $products = Product::query()
            ->where('is_active', true)
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('name', 'like', "%{$q}%")
                        ->orWhere('description', 'like', "%{$q}%");
                });
            })
            ->orderBy($sortColumn, $dir)
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Products fetched.',
            'errors' => null,
            'data' => ProductResource::collection($products)->response()->getData(true),
        ]);
    }

    /**
     * GET /api/products/{product}
     * Public show (frontend-ready)
     * - 404 if not active
     * - includes variants
     * - includes has_keys for digital products
     */
    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'variants' => function ($q) {
                // Se hai is_active sulle varianti, scommenta:
                // $q->where('is_active', true);
                $q->orderBy('id');
            }
        ]);

        $hasKeys = false;

        // Disponibilità keys per digital: is_used = false
        if ($product->type === 'digital') {
            $hasKeys = $product->keys()
                ->where('is_used', false)
                ->exists();
        }

        // property runtime letta dal ProductResource
        $product->has_keys = $hasKeys;

        return response()->json([
            'success' => true,
            'message' => 'Product fetched.',
            'errors' => null,
            'data' => (new ProductResource($product))->resolve(),
        ]);
    }
}

