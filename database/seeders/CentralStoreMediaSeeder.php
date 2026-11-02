<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Media\MediaType;
use App\Enums\Theme\AssetTypeEnum;
use App\Models\Asset\StoreAsset;
use App\Models\Media\Media;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Import only REAL generated, trusted store-brand assets into the new media system. */
class CentralStoreMediaSeeder extends Seeder
{
    public function run(): void
    {
        $sourceDisk = config('filesystems.default');
        $targetDisk = 'public';

        Store::query()->chunkById(50, function ($stores) use ($sourceDisk, $targetDisk): void {
            foreach ($stores as $store) {
                $assets = StoreAsset::withoutGlobalScopes()
                    ->where('store_id', $store->id)
                    ->whereIn('type', [
                        AssetTypeEnum::LOGO->value,
                        AssetTypeEnum::FAVICON->value,
                        AssetTypeEnum::BANNER->value,
                    ])->orderBy('id')->get();

                foreach ($assets as $asset) {
                    $role = match ($asset->type) {
                        AssetTypeEnum::LOGO => 'logo',
                        AssetTypeEnum::FAVICON => 'icon',
                        AssetTypeEnum::BANNER => 'banner',
                        default => null,
                    };
                    if ($role === null || $store->mediaLinks()->where('role', $role)->exists()) {
                        continue;
                    }
                    // Storage was deliberately omitted from the supplied ZIP.
                    // Do not insert media rows for files that do not exist.
                    if (!$asset->file_path || !Storage::disk($sourceDisk)->exists($asset->file_path)) {
                        continue;
                    }

                    $bytes = Storage::disk($sourceDisk)->get($asset->file_path);
                    $extension = pathinfo($asset->file_path, PATHINFO_EXTENSION) ?: 'svg';
                    $path = "stores/{$store->id}/media/seed-{$asset->id}.{$extension}";
                    if (!Storage::disk($targetDisk)->put($path, $bytes)) {
                        continue;
                    }

                    DB::transaction(function () use ($store, $asset, $bytes, $role, $path, $targetDisk): void {
                        $media = Media::query()->create([
                            'owner_store_id' => $store->id,
                            'type' => MediaType::Image,
                            'disk' => $targetDisk,
                            'path' => $path,
                            'mime_type' => $asset->mime_type ?: 'image/svg+xml',
                            'size_bytes' => strlen($bytes),
                            'width' => $asset->width,
                            'height' => $asset->height,
                            'checksum' => hash('sha256', $bytes),
                        ]);
                        $store->mediaLinks()->create([
                            'media_id' => $media->id,
                            'role' => $role,
                            'sort_order' => 0,
                        ]);
                    });
                }
            }
        });
    }
}
