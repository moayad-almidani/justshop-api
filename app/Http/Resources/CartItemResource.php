<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    public function toArray($request)
    {
        $variant = $this->productVariant;
        $product = $variant->product;
        $locale = app()->getLocale();

        // ── Translated product name & slug ──
        $translation = $product->translations->where('locale', $locale)->first()
            ?? $product->translations->first();

        $productName = $translation?->name ?? '';
        $productSlug = $translation?->slug ?? '';

        // ── Variant primary image ──
        $primaryImage = $variant->images->where('is_primary', true)->first()
            ?? $variant->images->first();
        $imageUrl = $variant->primaryImageUrl
            ?? ($product->relationLoaded('mediaLinks')
                ? $product->mediaLinks->first(fn ($link) => $link->media?->type === \App\Enums\Media\MediaType::Image)?->media?->url()
                : null)
            ?? ($primaryImage ? $primaryImage->full_url : null);

        // ── Translated attributes ──
        $attributes = $variant->optionValues->map(function ($ov) {
            return [
                'name'  => $ov->option?->name,
                'value' => $ov->value,
            ];
        });

        return [
            'id'           => $this->id,
            'quantity'     => $this->quantity,

            // ── Top-level fields (what your Nuxt CartItem interface expects) ──
            'name'         => $productName,
            'image'        => $imageUrl,
            'price'        => $variant->price,
            'max_quantity' => $variant->quantity,

            // ── Nested objects (for getItemByProductId + product page link) ──
            'product' => [
                'id'   => $product->id,
                'name' => $productName,
                'slug' => $productSlug,
            ],

            'variant' => [
                'id'         => $variant->id,
                'sku'        => $variant->sku,
                'price'      => $variant->price,
                'stock'      => $variant->quantity,
                'image'      => $imageUrl,
                'attributes' => $attributes,
            ],

            'subtotal' => round($this->quantity * $variant->price, 2),
        ];
    }
}