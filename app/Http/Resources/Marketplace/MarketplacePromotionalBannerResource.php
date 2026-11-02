<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplacePromotionalBannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->string('locale')->toString() ?: app()->getLocale();
        $translation = $this->translation($locale);

        return [
            'id' => $this->id,
            'title' => $translation?->title,
            'subtitle' => $translation?->subtitle,
            'button_text' => $translation?->button_text,
            'image_url' => $this->media?->url(),
            'target_url' => $this->target_url,
            'sort_order' => (int) $this->sort_order,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
        ];
    }
}
