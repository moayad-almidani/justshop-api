<?php

declare(strict_types=1);

namespace App\DTOs\Media;

final readonly class SetMainAttachmentDTO
{
    public function __construct(
        public int $storeId,
        public string $entity,
        public int $entityId,
        public int $attachmentId,
    ) {}
}
