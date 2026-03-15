<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/* MODELS */
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductKey;
use App\Models\OrderItem;
use App\Models\User;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'total_amount',
        'status',
        'payment_reference',
        'coupon_code',
        'discount_type',
        'discount_value',
        'discount_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    /* =======================
     * RELAZIONI
     * ======================= */

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productKeys(): HasMany
    {
        return $this->hasMany(ProductKey::class);
    }

    /* =======================
     * CARRELLO (NO PREZZI)
     * ======================= */

    public function addProduct(Product $product, int $quantity = 1, ?int $variantId = null): void
    {
        $this->ensurePending();
        $this->ensureValidQuantity($quantity);
        $this->validateProductForCart($product, $variantId);

        DB::transaction(function () use ($product, $quantity, $variantId) {
            $item = $this->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $item->update([
                    'quantity' => $item->quantity + $quantity,
                ]);
            } else {
                $this->items()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'quantity' => $quantity,
                ]);
            }

            $this->normalizeItems();
        });
    }

    public function changeQuantity(Product $product, int $quantity, ?int $variantId = null): void
    {
        $this->ensurePending();
        $this->ensureValidQuantity($quantity);

        DB::transaction(function () use ($product, $quantity, $variantId) {
            $item = $this->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new RuntimeException('Prodotto non presente nell’ordine');
            }

            $item->update([
                'quantity' => $quantity,
            ]);

            $this->normalizeItems();
        });
    }

    public function removeProduct(Product $product, ?int $variantId = null): void
    {
        $this->ensurePending();

        DB::transaction(function () use ($product, $variantId) {
            $item = $this->items()
                ->where('product_id', $product->id)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new RuntimeException('Prodotto non presente nell’ordine');
            }

            $item->delete();

            $this->normalizeItems();
        });
    }

    public function removeProductByItemId(int $itemId): void
    {
        $this->ensurePending();

        DB::transaction(function () use ($itemId) {
            $item = $this->items()
                ->where('id', $itemId)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new RuntimeException('Riga carrello non trovata');
            }

            $item->delete();

            $this->normalizeItems();
        });
    }

    /* =======================
     * NORMALIZZAZIONE
     * ======================= */

    protected function normalizeItems(): void
    {
        $items = $this->items()->get();

        $groups = $items->groupBy(fn ($i) =>
            $i->product_id . ':' . ($i->product_variant_id ?? 'null')
        );

        foreach ($groups as $group) {
            if ($group->count() <= 1) {
                continue;
            }

            $main = $group->first();

            $main->update([
                'quantity' => $group->sum('quantity'),
            ]);

            $group->slice(1)->each->delete();
        }
    }

    /* =======================
     * VALIDAZIONI
     * ======================= */

    protected function ensurePending(): void
    {
        if ($this->status !== 'pending') {
            throw new RuntimeException('Ordine non modificabile');
        }
    }

    protected function ensureValidQuantity(int $quantity): void
    {
        if ($quantity < 1) {
            throw new RuntimeException('Quantità non valida');
        }
    }

    protected function validateProductForCart(Product $product, ?int $variantId): void
    {
        $product->refresh();

        if (isset($product->is_active) && ! $product->is_active) {
            throw new RuntimeException('Prodotto non disponibile');
        }

        if (! in_array($product->type, ['digital', 'physical'], true)) {
            throw new RuntimeException('Tipo prodotto non valido');
        }

        if ($variantId !== null) {
            $variant = ProductVariant::where('id', $variantId)
                ->where('product_id', $product->id)
                ->first();

            if (! $variant) {
                throw new RuntimeException('Variante non valida');
            }
        }
    }

    /* =======================
     * CHECKOUT (SNAPSHOT)
     * ======================= */

    public function checkout(string $paymentReference): void
    {
        DB::transaction(function () use ($paymentReference) {
            $this->refresh();

            if ($this->status !== 'pending') {
                throw new RuntimeException('Ordine non modificabile');
            }

            if ($this->items()->count() === 0) {
                throw new RuntimeException('Ordine vuoto');
            }

            $this->snapshotPrices();
            $this->recalculateTotal();

            $this->update([
                'status' => 'paid',
                'payment_reference' => $paymentReference,
            ]);

            $this->assignProductKeys();
        });
    }

    protected function snapshotPrices(): void
    {
        $this->load(['items.product', 'items.variant']);

        foreach ($this->items as $item) {
            $price = $item->product->base_price;

            if ($item->variant) {
                if ($item->variant->price !== null) {
                    $price = $item->variant->price;
                } elseif ($item->variant->additional_cost !== null) {
                    $price += $item->variant->additional_cost;
                }
            }

            $item->update([
                'unit_price' => $price,
            ]);
        }
    }

    /* =======================
     * TOTALI (SOLO POST SNAPSHOT)
     * ======================= */

    protected function recalculateTotal(): void
    {
        $subtotal = $this->items()
            ->selectRaw('SUM(quantity * unit_price) as subtotal')
            ->value('subtotal') ?? 0;

        $discount = $this->calculateDiscount((float) $subtotal);

        $this->update([
            'discount_amount' => $discount,
            'total_amount' => max($subtotal - $discount, 0),
        ]);
    }

    protected function calculateDiscount(float $subtotal): float
    {
        if (! $this->discount_type || ! $this->discount_value) {
            return 0;
        }

        return $this->discount_type === 'percent'
            ? round($subtotal * ($this->discount_value / 100), 2)
            : min($this->discount_value, $subtotal);
    }

    /* =======================
     * PRODUCT KEYS
     * ======================= */

    protected function assignProductKeys(): void
    {
        $this->loadMissing(['items.product']);

        foreach ($this->items as $item) {
            if ($item->product->type !== 'digital') {
                continue;
            }

            for ($i = 0; $i < $item->quantity; $i++) {
                $key = ProductKey::where('product_id', $item->product_id)
                    ->where('is_used', false)
                    ->lockForUpdate()
                    ->first();

                if (! $key) {
                    throw new RuntimeException('Nessuna key disponibile');
                }

                $key->update([
                    'is_used' => true,
                    'used_at' => now(),
                    'order_id' => $this->id,
                ]);
            }
        }
    }
}
