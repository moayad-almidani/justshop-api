<?php

declare(strict_types=1);

namespace App\Repositories\Media;

use App\Models\Category;
use App\Models\Media\CategoryMedia;
use App\Models\Media\MediaAttachment;
use App\Models\Media\ProductMedia;
use App\Models\Media\ProductVariantMedia;
use App\Models\Media\StoreMedia;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MediaAttachmentRepository
{
    /** @return Model&object */
    public function findTarget(int $storeId, string $entity, int $entityId): Model
    {
        return match ($entity) {
            'products' => Product::query()->where('store_id', $storeId)->findOrFail($entityId),
            'variants' => ProductVariant::query()->whereHas('product', fn ($query) => $query->where('store_id', $storeId))->findOrFail($entityId),
            'categories' => Category::query()->where('store_id', $storeId)->findOrFail($entityId),
            'stores' => Store::query()->whereKey($storeId)->findOrFail($entityId),
            default => abort(404),
        };
    }

    public function relation(Model $target, string $entity): HasMany
    {
        return match ($entity) {
            'products' => $target->mediaLinks(),
            'variants' => $target->mediaLinks(),
            'categories' => $target->mediaLinks(),
            'stores' => $target->mediaLinks(),
            default => abort(404),
        };
    }

    public function findAttachment(Model $target, string $entity, int $attachmentId): MediaAttachment
    {
        return $this->relation($target, $entity)->findOrFail($attachmentId);
    }

    public function assertExclusive(MediaAttachment $attachment): void
    {
        $mediaId = (int) $attachment->media_id;
        // Defensive guard for any future attachment-by-id operation: different
        // link tables have independent UNIQUE(media_id) constraints.
        $references = [
            'product_media' => 'media_id',
            'product_variant_media' => 'media_id',
            'store_media' => 'media_id',
            'category_media' => 'media_id',
            'story_media' => 'media_id',
            'promotional_banners' => 'image_media_id',
        ];

        foreach ($references as $table => $column) {
            if ($table !== $attachment->getTable() && DB::table($table)->where($column, $mediaId)->exists()) {
                throw ValidationException::withMessages(['media_id' => ['Media is already attached to another entity.']]);
            }
        }
    }
}
