<?php

declare(strict_types=1);

namespace App\DTOs\Media;

use App\Http\Requests\Merchant\Media\UploadAttachmentRequest;
use Illuminate\Http\UploadedFile;

final readonly class UploadAttachmentDTO
{
    public function __construct(
        public int $storeId,
        public string $entity,
        public int $entityId,
        public UploadedFile $file,
        public ?string $role,
        public bool $isMain,
        public int $sortOrder,
        public ?string $altText = null,
    ) {}

    public static function fromRequest(UploadAttachmentRequest $request, int $storeId, string $entity, int $entityId): self
    {
        $data = $request->validated();

        return new self(
            storeId: $storeId,
            entity: $entity,
            entityId: $entityId,
            file: $request->file('file'),
            role: $data['role'] ?? null,
            isMain: (bool) ($data['is_main'] ?? false),
            sortOrder: (int) ($data['sort_order'] ?? 0),
            altText: $data['alt_text'] ?? null,
        );
    }
}
