<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceStoryGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'store' => (new MarketplaceStoryStoreResource($this->resource))->resolve($request),
            'story_count' => $this->stories->count(),
            'stories' => $this->stories
                ->map(fn ($story) => (new MarketplaceStoryResource($story))->resolve($request))
                ->values()
                ->all(),
        ];
    }
}
