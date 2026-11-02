<?php

declare(strict_types=1);

namespace App\DTOs\Story;

final readonly class DeleteStoreStoryDTO
{
    public function __construct(
        public int $storeId,
        public int $storyId,
    ) {}
}
