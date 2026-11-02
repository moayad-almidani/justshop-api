<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logo = $this->mediaLinks
            ->first(fn ($link) => $link->role === 'logo' && $link->media !== null)
            ?->media?->url();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $logo ?: $this->logo_url,
            'currency' => $this->currency,
            'active_products_count' => isset($this->active_products_count)
                ? (int) $this->active_products_count
                : null,
        ];
    }
}
