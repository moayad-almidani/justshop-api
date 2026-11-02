<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal Store representation used only inside public Story groups.
 *
 * Keep MarketplaceStoreResource unchanged: the full Store listing still
 * returns currency and active_products_count.
 */
class MarketplaceStoryStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mediaLinks = $this->resource->relationLoaded('mediaLinks')
            ? $this->resource->getRelation('mediaLinks')
            : collect();

        $logo = $mediaLinks
            ->first(fn ($link) => $link->role === 'logo' && $link->media !== null)
            ?->media?->url();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $logo ?: $this->logo_url,
        ];
    }
}
