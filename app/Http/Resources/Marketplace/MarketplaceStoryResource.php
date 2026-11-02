<?php

declare(strict_types=1);

namespace App\Http\Resources\Marketplace;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MarketplaceStoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $attachment = $this->mediaAttachment;
        $media = $attachment?->media;
        $locale = $request->string('locale')->toString() ?: app()->getLocale();
        $taggedProduct = $this->product;
        if ($taggedProduct !== null && (
            ! $taggedProduct->is_active || (int) $taggedProduct->store_id !== (int) $this->store_id
        )) {
            $taggedProduct = null;
        }
        $productTranslation = $taggedProduct?->translation($locale);

        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'is_expired' => $this->expires_at !== null && $this->expires_at->isPast(),
            'caption' => $this->caption,
            'media' => $media ? [
                'id' => $media->id,
                'type' => $media->type?->value,
                'url' => $media->url(),
                'mime_type' => $media->mime_type,
                'width' => $media->width,
                'height' => $media->height,
                'duration_sec' => $media->duration_sec,
                'alt_text' => $attachment?->alt_text,
            ] : null,
            'product' => $taggedProduct ? [
                'id' => $taggedProduct->id,
                'name' => $productTranslation?->name ?? $taggedProduct->translations->first()?->name,
                'slug' => $productTranslation?->slug ?? $taggedProduct->translations->first()?->slug,
            ] : null,
            'published_at' => $this->published_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
        ];
    }
}
