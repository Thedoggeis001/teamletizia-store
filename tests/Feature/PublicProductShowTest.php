<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductKey;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_product_show_returns_variants_and_has_keys_true_when_unused_keys_exist(): void
    {
        $product = Product::factory()->create([
            'type' => 'digital',
        ]);

        ProductVariant::factory()->count(2)->create([
            'product_id' => $product->id,
        ]);

        ProductKey::factory()->create([
            'product_id' => $product->id,
            'key_value' => 'AAAA-BBBB-CCCC',
            'is_used' => false,
        ]);

        ProductKey::factory()->create([
            'product_id' => $product->id,
            'key_value' => 'DDDD-EEEE-FFFF',
            'is_used' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();

        $response->assertJsonStructure([
            'data' => [
                'id',
                'variants',
                'has_keys',
            ],
        ]);

        $this->assertTrue((bool) $response->json('data.has_keys'));
        $this->assertCount(2, $response->json('data.variants'));
    }

    public function test_public_product_show_has_keys_false_when_no_unused_keys_exist(): void
    {
        $product = Product::factory()->create([
            'type' => 'digital',
        ]);

        ProductKey::factory()->create([
            'product_id' => $product->id,
            'key_value' => 'ZZZZ-YYYY-XXXX',
            'is_used' => true,
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertOk();

        $response->assertJsonStructure([
            'data' => ['has_keys'],
        ]);

        $this->assertFalse((bool) $response->json('data.has_keys'));
    }
}
