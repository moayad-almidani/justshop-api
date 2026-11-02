<?php

namespace App\Http\Resources;

use App\Support\Media\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_variant_id' => $this->product_variant_id,
            'slug' => $this->slug,
            'category_id' => $this->category_id,
            'primary_image' => $this->central_media_path
                ? Storage::disk($this->central_media_disk)->url($this->central_media_path)
                : $this->formatImageUrl($this->primary_image),
            'alt_text' => $this->alt_text,
            'product_name' => $this->product_name,
            'price' => $this->price,
            'description' => $this->description,
            'total_sold' => $this->total_sold,
        ];
    }

    /**
     * Format image URL to include /storage/ prefix for local paths.
     * Mirrors the Image model's getFullUrlAttribute() logic.
     */
    private function formatImageUrl(?string $path): ?string
    {
        return MediaUrl::resolve($path);
    }
}
