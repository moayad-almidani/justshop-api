<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\DTOs\Media\SetMainAttachmentDTO;
use App\Enums\Media\MediaType;
use App\Models\Media\MediaAttachment;
use App\Repositories\Media\MediaAttachmentRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetMainAttachmentAction
{
    public function __construct(private MediaAttachmentRepository $repository) {}

    public function execute(SetMainAttachmentDTO $dto): MediaAttachment
    {
        if (!in_array($dto->entity, ['products', 'variants'], true)) {
            abort(404);
        }

        return DB::transaction(function () use ($dto): MediaAttachment {
            $target = $this->repository->findTarget($dto->storeId, $dto->entity, $dto->entityId);
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $relation = $this->repository->relation($target, $dto->entity);
            $attachment = $this->repository->findAttachment($target, $dto->entity, $dto->attachmentId);
            $attachment->load('media');
            if ($attachment->media?->type !== MediaType::Image) {
                throw ValidationException::withMessages(['attachment_id' => ['Only images can be the main product media.']]);
            }
            $relation->where('is_main', 1)->update(['is_main' => null]);
            $attachment->update(['is_main' => true]);

            return $attachment->refresh()->load('media');
        });
    }
}
