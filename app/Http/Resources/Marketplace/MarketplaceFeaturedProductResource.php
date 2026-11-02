<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use App\Enums\Media\MediaType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceFeaturedProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->string('locale')->toString() ?: app()->getLocale();
        $variant = $this->variants->firstWhere('id', (int) $this->product_variant_id)
            ?? $this->variants->first();
        $categoryTranslation = $this->category?->translation($locale);

        $variantImage = $this->centralImageUrl($variant?->mediaLinks ?? collect());
        $productImage = $this->centralImageUrl($this->mediaLinks);
        $legacyVariantImage = $variant?->images?->firstWhere('is_primary', true)
            ?? $variant?->images?->first();
        $legacyProductImage = $this->images->firstWhere('is_primary', true)
            ?? $this->images->first();

        $imageUrl = $variantImage
            ?? $productImage
            ?? $legacyVariantImage?->full_url
            ?? $legacyProductImage?->full_url;

        $price = $variant?->price;
        $compareAtPrice = $variant?->compare_at_price;
        $inStock = $variant !== null && (! $variant->track_inventory || $variant->quantity > 0);
        $discountPercentage = $this->discountPercentage($price, $compareAtPrice);

        return [
            'id' => $this->id,
            'name' => $this->translated('name', $locale) ?? $this->translations->first()?->name,
            'slug' => $this->translated('slug', $locale) ?? $this->translations->first()?->slug,
            'image_url' => $imageUrl,
            'price' => $price !== null ? (float) $price : null,
            'compare_at_price' => $compareAtPrice !== null ? (float) $compareAtPrice : null,
            'currency' => $this->store?->currency,
            'in_stock' => $inStock,
            'is_featured' => (bool) $this->is_featured,
            'is_new' => (bool) $this->is_new,
            'discount_percentage' => $discountPercentage,
            'store' => $this->store ? [
                'id' => $this->store->id,
                'name' => $this->store->name,
                'slug' => $this->store->slug,
                'logo_url' => $this->store->mediaLinks
                    ->first(fn ($link) => $link->role === 'logo' && $link->media !== null)
                    ?->media?->url() ?: $this->store->logo_url,
            ] : null,
            'category' => $this->category ? [
                'id' => $this->category->id,
                'name' => $categoryTranslation?->name ?? $this->category->slug,
                'slug' => $categoryTranslation?->slug ?? $this->category->slug,
            ] : null,
        ];
    }

    /**
     * Calculate the discount for the exact available variant whose price is
     * returned in this resource. No discount is exposed when compare_at_price
     * is missing, zero, or not higher than the selling price.
     */
    private function discountPercentage(?float $price, ?float $compareAtPrice): ?int
    {
        if (
            $price === null
            || $compareAtPrice === null
            || $compareAtPrice <= 0
            || $compareAtPrice <= $price
        ) {
            return null;
        }

        return (int) round((($compareAtPrice - $price) / $compareAtPrice) * 100);
    }

    private function centralImageUrl(iterable $attachments): ?string
    {
        $images = collect($attachments)->filter(
            fn ($link) => $link->media !== null && $link->media->type === MediaType::Image
        );

        $main = $images->first(fn ($link) => (bool) $link->getAttribute('is_main'));
        $selected = $main ?? $images->first();

        return $selected?->media?->url();
    }
}
