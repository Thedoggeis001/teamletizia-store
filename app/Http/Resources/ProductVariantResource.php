<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price !== null ? (float) $this->price : null,
            'additional_cost' => $this->additional_cost !== null ? (float) $this->additional_cost : null,
        ];
    }
}
