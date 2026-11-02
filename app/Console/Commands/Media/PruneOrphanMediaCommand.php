<?php

declare(strict_types=1);

namespace App\Console\Commands\Media;

use App\Models\Media\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class PruneOrphanMediaCommand extends Command
{
    protected $signature = 'media:prune {--days=7 : Minimum age of an orphaned media record}';

    protected $description = 'Permanently delete unreferenced central media files after a safety period.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = 0;

        Media::withTrashed()
            ->where('created_at', '<', now()->subDays($days))
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$deleted): void {
                foreach ($items as $item) {
                    DB::transaction(function () use ($item, &$deleted): void {
                        $media = Media::withTrashed()->whereKey($item->id)->lockForUpdate()->first();
                        if (!$media) {
                            return;
                        }

                        $references = [
                            'product_media' => 'media_id',
                            'product_variant_media' => 'media_id',
                            'store_media' => 'media_id',
                            'category_media' => 'media_id',
                            'story_media' => 'media_id',
                            'promotional_banners' => 'image_media_id',
                        ];
                        foreach ($references as $table => $column) {
                            if (DB::table($table)->where($column, $media->id)->exists()) {
                                return;
                            }
                        }

                        // Only remove assets from application-managed upload/seed prefixes.
                        $managedPath = str_starts_with($media->path, 'stores/')
                            || str_starts_with($media->path, 'marketplace/banners/');
                        if (! $managedPath) {
                            return;
                        }

                        foreach ($media->conversions as $conversion) {
                            if (!Storage::disk($conversion->disk)->delete($conversion->path)
                                && Storage::disk($conversion->disk)->exists($conversion->path)) {
                                return;
                            }
                        }
                        if (!Storage::disk($media->disk)->delete($media->path)
                            && Storage::disk($media->disk)->exists($media->path)) {
                            return;
                        }

                        $media->forceDelete();
                        ++$deleted;
                    });
                }
            });

        $this->info("Pruned {$deleted} orphan media records.");

        return self::SUCCESS;
    }
}
