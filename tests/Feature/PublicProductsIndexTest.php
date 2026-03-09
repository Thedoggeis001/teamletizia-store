<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicProductsIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_products_index_returns_200_and_data_array(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/products');

        $response->assertOk();
        $response->assertJsonStructure(['data']);

        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));
    }
}

