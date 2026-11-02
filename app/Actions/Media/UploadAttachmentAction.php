<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\DTOs\Media\UploadAttachmentDTO;
use App\Enums\Media\MediaType;
use App\Models\Media\MediaAttachment;
use App\Models\Media\Media;
use App\Repositories\Media\MediaAttachmentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class UploadAttachmentAction
{
    public function __construct(private MediaAttachmentRepository $repository) {}

    public function execute(UploadAttachmentDTO $dto): MediaAttachment
    {
        $target = $this->repository->findTarget($dto->storeId, $dto->entity, $dto->entityId);
        $mime = (string) $dto->file->getMimeType();
        $type = str_starts_with($mime, 'video/') ? MediaType::Video : MediaType::Image;
        $disk = 'public';
        $path = $dto->file->store("stores/{$dto->storeId}/media", $disk);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Failed to store the uploaded media.');
        }

        try {
            $dimensions = $type === MediaType::Image ? @getimagesize($dto->file->getRealPath()) : false;

            return DB::transaction(function () use ($dto, $target, $type, $disk, $path, $mime, $dimensions): MediaAttachment {
                // Serialization of main-image switches within the same target.
                $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

                $media = Media::create([
                    'owner_store_id' => $dto->storeId,
                    'type' => $type,
                    'disk' => $disk,
                    'path' => $path,
                    'mime_type' => $mime,
                    'size_bytes' => $dto->file->getSize(),
                    'width' => is_array($dimensions) ? $dimensions[0] : null,
                    'height' => is_array($dimensions) ? $dimensions[1] : null,
                    'checksum' => hash_file('sha256', $dto->file->getRealPath()),
                ]);

                $relation = $this->repository->relation($target, $dto->entity);
                $isProduct = in_array($dto->entity, ['products', 'variants'], true);
                $isFirst = $isProduct && ! $relation->exists();
                if ($isProduct && $dto->isMain) {
                    $relation->where('is_main', 1)->update(['is_main' => null]);
                }

                $values = [
                    'media_id' => $media->id,
                    'sort_order' => $dto->sortOrder,
                    'alt_text' => $dto->altText,
                ];
                if ($isProduct) {
                    $values['is_main'] = ($dto->isMain || ($isFirst && $type === MediaType::Image)) ? true : null;
                } else {
                    $values['role'] = $dto->role ?? 'gallery';
                    if (in_array($values['role'], ['logo', 'icon', 'banner'], true)) {
                        // A storefront has one active logo/icon/banner per role.
                        // Old media rows remain for the cleanup job (no immediate byte deletion).
                        foreach ($relation->where('role', $values['role'])->with('media')->get() as $old) {
                            $oldMedia = $old->media;
                            $old->delete();
                            $oldMedia?->delete();
                        }
                    }
                }

                $link = $relation->create($values);
                return $link->load('media');
            });
        } catch (\Throwable $error) {
            Storage::disk($disk)->delete($path); // Compensation if DB write fails.
            throw $error;
        }
    }
}
