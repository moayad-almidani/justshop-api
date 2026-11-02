<?php

declare(strict_types=1);

namespace App\Enums\Story;

enum StoryStatusEnum: string
{
    case DRAFT = 'draft';
    case PUBLISHED = 'published';
    case ARCHIVED = 'archived';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
