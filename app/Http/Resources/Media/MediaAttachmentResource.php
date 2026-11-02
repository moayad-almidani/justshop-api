<?php

declare(strict_types=1);

namespace App\Http\Resources\Media;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $file = $this->media;

        return [
            'id' => $this->id, // attachment id, for DELETE
            'media_id' => $file?->id,
            'type' => $file?->type?->value,
            'url' => $file?->url(),
            'mime_type' => $file?->mime_type,
            'size_bytes' => $file?->size_bytes,
            'width' => $file?->width,
            'height' => $file?->height,
            'role' => $this->getAttribute('role'),
            'is_main' => (bool) $this->getAttribute('is_main'),
            'sort_order' => $this->sort_order,
            'alt_text' => $this->alt_text,
        ];
    }
}
