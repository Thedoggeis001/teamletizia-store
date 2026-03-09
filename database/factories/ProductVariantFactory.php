<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->word(),          // <-- NOT NULL
            'price' => $this->faker->randomFloat(2, 1, 200), // se non esiste nel DB, dimmelo
        ];
    }
}
