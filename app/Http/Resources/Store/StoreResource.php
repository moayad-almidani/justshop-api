<?php

namespace App\Http\Resources\Store;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'slug'               => $this->slug,
            'status'             => $this->status->value,
            'is_active'          => $this->isOperational(),
            'status_changed_at'  => $this->status_changed_at ? $this->status_changed_at->toIso8601String() : null,
            'created_at'         => $this->created_at->toIso8601String(),
            'domain'             => $this->domain,
            'media' => $this->whenLoaded('mediaLinks', fn () => \App\Support\Media\CentralGallery::format($this->mediaLinks)->all()),
            'logo_url' => $this->whenLoaded('mediaLinks', fn () => $this->mediaLinks->first(fn ($link) => $link->role === 'logo' && $link->media)?->media?->url()),
            'banner_url' => $this->whenLoaded('mediaLinks', fn () => $this->mediaLinks->first(fn ($link) => $link->role === 'banner' && $link->media)?->media?->url()),
            'currency'           => $this->currency ?? 'USD',
            'timezone'           => $this->timezone ?? 'UTC',
        ];
    }
}
