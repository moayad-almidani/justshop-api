<?php
// app/Http/Resources/OrderItemResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class OrderItemResource extends JsonResource
{
    public function toArray($request)
    {
        $variant = $this->whenLoaded('productVariant', function () {
            return $this->productVariant;
        });
        $variantLoaded = !($variant instanceof MissingValue);

        // Get variant image if loaded
        $imageUrl = null;
        if ($variantLoaded && $variant->relationLoaded('images')) {
            $primaryImage = $variant->images->where('is_primary', true)->first()
                ?? $variant->images->first();
            $imageUrl = $primaryImage ? $primaryImage->full_url : null;
        }

        if ($variantLoaded && $variant) {
            $imageUrl = ($variant->relationLoaded('mediaLinks') ? $variant->primaryImageUrl : null)
                ?? ($variant->relationLoaded('product') && $variant->product?->relationLoaded('mediaLinks')
                    ? $variant->product->mediaLinks->first(fn ($link) => $link->media?->type === \App\Enums\Media\MediaType::Image)?->media?->url()
                    : null)
                ?? $imageUrl;
        }

        // Get product slug if loaded
        $slug = null;
        if ($variantLoaded && $variant->relationLoaded('product')) {
            $product = $variant->product;
            if ($product && $product->relationLoaded('translations')) {
                $locale = app()->getLocale();
                $translation = $product->translations->where('locale', $locale)->first()
                    ?? $product->translations->first();
                $slug = $translation?->slug;
            }
        }

        return [
            'id'                      => $this->id,
            'product_id'              => $this->product_id,
            'product_variant_id'      => $this->product_variant_id,
            'product_name'            => $this->product_name,
            'product_slug'            => $slug,
            'sku'                     => $this->sku,
            'image'                   => $imageUrl,
            'unit_price'              => (float) $this->unit_price,
            'unit_discount_percentage' => (float) $this->unit_discount_percentage,
            'quantity'                => $this->quantity,
            'subtotal'                => round((float) $this->unit_price * $this->quantity, 2),
            'attributes'              => $this->attributes,

            // For reorder: is the variant still available?
            'is_available'            => $variantLoaded ? ($variant->is_active && $variant->quantity > 0) : false,
        ];
    }
}
