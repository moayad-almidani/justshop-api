<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Repositories\Media\MediaAttachmentRepository;
use Illuminate\Support\Facades\DB;

final class DeleteAttachmentAction
{
    public function __construct(private MediaAttachmentRepository $repository) {}

    public function execute(int $storeId, string $entity, int $entityId, int $attachmentId): void
    {
        DB::transaction(function () use ($storeId, $entity, $entityId, $attachmentId): void {
            $target = $this->repository->findTarget($storeId, $entity, $entityId);
            $target->newQuery()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $attachment = $this->repository->findAttachment($target, $entity, $attachmentId);
            $media = $attachment->media;
            $attachment->delete();
            // The source bytes are deliberately NOT deleted inline; the prune job
            // handles detached media after a grace period.
            if ($media) {
                $media->delete();
            }
        });
    }
}
