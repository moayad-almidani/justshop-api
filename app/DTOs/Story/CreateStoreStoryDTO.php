<?php

declare(strict_types=1);

namespace App\DTOs\Story;

use App\Http\Requests\Merchant\Story\CreateStoreStoryRequest;
use Illuminate\Http\UploadedFile;

final readonly class CreateStoreStoryDTO
{
    public function __construct(
        public int $storeId,
        public UploadedFile $file,
        public ?string $caption,
        public ?int $productId,
        public ?string $altText,
    ) {}

    public static function fromRequest(CreateStoreStoryRequest $request, int $storeId): self
    {
        $data = $request->validated();

        return new self(
            storeId: $storeId,
            file: $request->file('file'),
            caption: $data['caption'] ?? null,
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            altText: $data['alt_text'] ?? null,
        );
    }
}
