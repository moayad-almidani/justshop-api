<?php
// app/Http/Resources/CategoryResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'media' => $this->whenLoaded('mediaLinks', fn () => \App\Support\Media\CentralGallery::format($this->mediaLinks)->all()),
            'image_url' => $this->whenLoaded('mediaLinks', fn () => $this->mediaLinks->first(fn ($link) => $link->media?->type === \App\Enums\Media\MediaType::Image)?->media?->url()),
            'parent' => new CategoryResource($this->whenLoaded('parent')),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
        ];
    }
}