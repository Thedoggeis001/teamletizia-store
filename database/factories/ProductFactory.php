<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'base_price' => $this->faker->randomFloat(2, 1, 200),
            // metti un valore valido rispetto alle tue regole (enum/validation)
            'type' => 'digital',
            'is_active' => true,
        ];
    }
}
