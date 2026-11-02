<?php

namespace App\Http\Resources\Admin\Product;

use App\Enums\Product\ProductStatusEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductResource extends JsonResource
{
    public function toArray($request): array
    {
        $defaultVariant = $this->whenLoaded('variants',
            fn() => $this->variants
                ->where('is_active', true)
                ->sortBy('price')
                ->first()
        );

        $totalStock = $this->whenLoaded('variants',
            fn() => $this->variants->sum('quantity')
        );

        $thumbnail = $this->whenLoaded('variants', function () {
            foreach ($this->variants as $variant) {
                if ($variant->relationLoaded('images')) {
                    $img = $variant->images
                        ->where('is_primary', true)
                        ->first()
                        ?? $variant->images->first();
                    if ($img) {
                        return $img->full_url;
                    }
                }
            }
            return null;
        });

        if ($this->relationLoaded('variants')) {
            foreach ($this->variants as $variant) {
                if ($variant->relationLoaded('mediaLinks') && $variant->mediaLinks->isNotEmpty()) {
                    $thumbnail = $variant->primaryImageUrl ?? $thumbnail;
                    break;
                }
            }
        }
        if ($this->relationLoaded('mediaLinks') && $this->mediaLinks->isNotEmpty()) {
            $thumbnail ??= $this->primaryImageUrl;
        }

        return [
            'id'         => $this->id,
            'name'       => $this->translation()?->name,
            'status'     => $this->is_active ? ProductStatusEnum::ACTIVE->value : ProductStatusEnum::DRAFT->value,
            'is_new'     => (bool) $this->is_new,
            'price'      => $defaultVariant
                ? (float) $defaultVariant->price
                : 0,
            'stock'      => $totalStock ?? 0,
            'thumbnail'  => $thumbnail ?? null,
            'category'   => $this->whenLoaded('category',
                fn() => $this->category?->name
            ),
            'created_at' => $this->created_at,
        ];
    }
}
