<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

final class CentralGallery
{
    /** Merge variant-specific images first, then shared product media. Never duplicate DB rows. */
    public static function forVariant(ProductVariant $variant): Collection
    {
        $variant->loadMissing('mediaLinks.media', 'product.mediaLinks.media');

        return self::format($variant->mediaLinks)
            ->concat(self::format($variant->product?->mediaLinks ?? collect()))
            ->values();
    }

    public static function format(iterable $attachments): Collection
    {
        return collect($attachments)
            ->filter(fn ($link) => $link->media !== null)
            ->map(fn ($link) => [
                'id' => $link->id,
                'media_id' => $link->media_id,
                'url' => $link->media->url(),
                'type' => $link->media->type->value,
                'is_primary' => (bool) $link->getAttribute('is_main'),
                'position' => $link->sort_order,
                'role' => $link->getAttribute('role'),
                'alt' => $link->alt_text,
                'alt_text' => $link->alt_text,
            ])
            ->values();
    }
}
