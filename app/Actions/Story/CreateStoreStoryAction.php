<?php

declare(strict_types=1);

namespace App\Actions\Story;

use App\DTOs\Story\CreateStoreStoryDTO;
use App\Enums\Media\MediaType;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Media\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\Story\Story;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class CreateStoreStoryAction
{
    public function execute(CreateStoreStoryDTO $dto): Story
    {
        // Store-scoped lookup prevents tagging a product from another merchant.
        if ($dto->productId !== null) {
            Product::query()
                ->where('store_id', $dto->storeId)
                ->where('is_active', true)
                ->findOrFail($dto->productId);
        }

        $mime = (string) $dto->file->getMimeType();
        $type = str_starts_with($mime, 'video/') ? MediaType::Video : MediaType::Image;
        $disk = 'public';
        $path = $dto->file->store("stores/{$dto->storeId}/stories", $disk);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Failed to store story media.');
        }

        try {
            $dimensions = $type === MediaType::Image
                ? @getimagesize($dto->file->getRealPath())
                : false;

            return DB::transaction(function () use ($dto, $mime, $type, $disk, $path, $dimensions): Story {
                Store::query()
                    ->whereKey($dto->storeId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $now = now();
                $media = Media::query()->create([
                    'owner_store_id' => $dto->storeId,
                    'type' => $type,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => $dto->file->getSize(),
                    'width' => is_array($dimensions) ? (int) $dimensions[0] : null,
                    'height' => is_array($dimensions) ? (int) $dimensions[1] : null,
                    'duration_sec' => null,
                    'checksum' => hash_file('sha256', $dto->file->getRealPath()),
                ]);

                $story = Story::query()->create([
                    'store_id' => $dto->storeId,
                    'product_id' => $dto->productId,
                    'status' => StoryStatusEnum::PUBLISHED,
                    'caption' => $dto->caption,
                    'published_at' => $now,
                    'expires_at' => $now->copy()->addHours(24),
                ]);

                $story->mediaAttachment()->create([
                    'media_id' => $media->id,
                    'alt_text' => $dto->altText,
                    'sort_order' => 0,
                ]);

                return $story->load(['mediaAttachment.media', 'product.translations']);
            });
        } catch (Throwable $error) {
            Storage::disk($disk)->delete($path);
            throw $error;
        }
    }
}
