<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminHappyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_happy_path_create_product_create_variant_import_keys_list_keys(): void
    {
        // Arrange: admin autenticato
        $admin = User::factory()->create(['is_admin' => true]);
        Sanctum::actingAs($admin);

        // 1) Create product
        $createProduct = $this->postJson('/api/admin/products', [
            'name' => 'Digital Game',
            'description' => 'A digital product for testing',
            'base_price' => 19.99,
            'type' => 'digital',
            'is_active' => true,
        ]);

        $createProduct->assertCreated();
        $createProduct->assertJsonStructure(['data' => ['id']]);
        $productId = (int) $createProduct->json('data.id');

        // 2) Create variant
        $createVariant = $this->postJson("/api/admin/products/{$productId}/variants", [
            'name' => 'Standard',
            'price' => 19.99,
        ]);

        $createVariant->assertCreated();

        // 3) Import keys
        $keys = ['AAAA-BBBB-CCCC', 'DDDD-EEEE-FFFF'];

        $importKeys = $this->postJson("/api/admin/products/{$productId}/keys/import", [
            'keys' => $keys,
        ]);

        $importKeys->assertCreated();

        // ✅ Verifica vera: le key sono state inserite in DB per questo prodotto
        $this->assertDatabaseHas('product_keys', [
            'product_id' => $productId,
            'key_value'  => 'AAAA-BBBB-CCCC',
        ]);

        $this->assertDatabaseHas('product_keys', [
            'product_id' => $productId,
            'key_value'  => 'DDDD-EEEE-FFFF',
        ]);

        // 4) List keys (non assumiamo count e non assumiamo che key_value sia visibile)
        $listKeys = $this->getJson("/api/admin/products/{$productId}/keys");

        $listKeys->assertOk();
        $listKeys->assertJsonStructure([
            'data',
        ]);

        $data = $listKeys->json('data');
        $this->assertIsArray($data);
        $this->assertNotEmpty($data);
    }
}
