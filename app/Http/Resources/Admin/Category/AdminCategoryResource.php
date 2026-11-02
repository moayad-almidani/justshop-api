<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Category;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();
        $translation = $this->translation($locale);

        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'sort_order'     => $this->sort_order,
            'is_active'      => $this->is_active,
            'store_id'       => $this->store_id,
            'parent_id'      => $this->parent_id,
            'media' => $this->whenLoaded('mediaLinks', fn () => \App\Support\Media\CentralGallery::format($this->mediaLinks)->all()),
            'image_url' => $this->whenLoaded('mediaLinks', fn () => $this->mediaLinks->first(fn ($link) => $link->media?->type === \App\Enums\Media\MediaType::Image)?->media?->url()),
            'translation'    => $translation ? [
                'locale' => $translation->locale,
                'name'   => $translation->name,
                'slug'   => $translation->slug,
            ] : null,
            'translations'   => $this->whenLoaded(
                'translations',
                fn() => $this->translations->map(fn($t) => [
                    'locale' => $t->locale,
                    'name'   => $t->name,
                    'slug'   => $t->slug,
                ])
            ),
            'parent'         => $this->whenLoaded(
                'parent',
                fn() => $this->parent ? [
                    'id'   => $this->parent->id,
                    'slug' => $this->parent->slug,
                    'name' => $this->parent->translation($locale)?->name
                        ?? $this->parent->slug,
                ] : null,
            ),
            'children'       => $this->whenLoaded(
                'children',
                fn() => AdminCategoryResource::collection($this->children),
            ),
            'products_count' => $this->whenCounted('products'),
            'breadcrumb'     => $this->when(
                isset($this->breadcrumb),
                fn() => $this->breadcrumb,
            ),
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
            'deleted_at'     => $this->deleted_at?->toISOString(),
        ];
    }
}
