<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'base_price' => (float) $this->base_price,

            // compare solo se impostata dal controller (show)
            'has_keys' => $this->when(
                isset($this->resource->has_keys),
                (bool) $this->resource->has_keys
            ),

            'variants' => ProductVariantResource::collection(
                $this->whenLoaded('variants')
            ),
        ];
    }
}

