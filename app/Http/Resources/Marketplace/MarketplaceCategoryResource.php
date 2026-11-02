<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $translation = $this->translation($request->string('locale')->toString() ?: app()->getLocale());
        $imageLink = $this->mediaLinks->first(
            fn ($link) => $link->role === 'icon' && $link->media !== null
        ) ?? $this->mediaLinks->first(fn ($link) => $link->media !== null);

        return [
            'id' => $this->id,
            'name' => $translation?->name ?? $this->slug,
            'slug' => $translation?->slug ?? $this->slug,
            'image_url' => $imageLink?->media?->url(),
            'sort_order' => $this->sort_order,
        ];
    }
}
