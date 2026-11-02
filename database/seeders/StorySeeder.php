<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Media\MediaType;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Media\Media;
use App\Models\Product;
use App\Models\Story\Story;
use App\Models\Story\StoryMedia;
use Database\Seeders\Concerns\SeedsDemoStore;
use Database\Seeders\Concerns\SeedsStaticMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Seeds visible demo stories so the Marketplace Home API is populated after db:seed. */
class StorySeeder extends Seeder
{
    use SeedsDemoStore;
    use SeedsStaticMedia;

    public function run(): void
    {
        $store = $this->demoStore();
        $products = Product::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->values();

        $stories = [
            [
                'key' => 'new-arrivals',
                'caption' => 'وصل حديثاً إلى المتجر',
                'asset' => 'marketplace/stories/story-new-arrivals.svg',
                'alt_text' => 'إعلان عن المنتجات الجديدة',
            ],
            [
                'key' => 'special-offer',
                'caption' => 'عرض خاص لفترة محدودة',
                'asset' => 'marketplace/stories/story-special-offer.svg',
                'alt_text' => 'إعلان عن عرض خاص',
            ],
            [
                'key' => 'best-sellers',
                'caption' => 'اكتشف المنتجات الأكثر مبيعاً',
                'asset' => 'marketplace/stories/story-best-sellers.svg',
                'alt_text' => 'المنتجات الأكثر مبيعاً',
            ],
        ];

        foreach ($stories as $index => $definition) {
            $path = "stores/{$store->id}/stories/demo-{$definition['key']}.svg";
            $this->seedStaticAsset($definition['asset'], $path, 'public');
            $bytes = Storage::disk('public')->get($path);

            $media = Media::withTrashed()->firstOrNew([
                'disk' => 'public',
                'path' => $path,
            ]);
            if ($media->exists && $media->trashed()) {
                $media->restore();
            }
            $media->fill([
                'owner_store_id' => $store->id,
                'type' => MediaType::Image,
                'mime_type' => 'image/svg+xml',
                'size_bytes' => strlen($bytes),
                'width' => 900,
                'height' => 1600,
                'duration_sec' => null,
                'checksum' => hash('sha256', $bytes),
            ])->save();

            $publishedAt = now()->subMinutes(($index + 1) * 5);
            $story = Story::withTrashed()->firstOrNew([
                'store_id' => $store->id,
                'caption' => $definition['caption'],
            ]);
            if ($story->exists && $story->trashed()) {
                $story->restore();
            }
            $story->fill([
                'product_id' => $products->get($index)?->id,
                'status' => StoryStatusEnum::PUBLISHED,
                'published_at' => $publishedAt,
                'expires_at' => $publishedAt->copy()->addHours(24),
            ])->save();

            $attachment = StoryMedia::query()->firstOrNew(['story_id' => $story->id]);
            $attachment->fill([
                'media_id' => $media->id,
                'alt_text' => $definition['alt_text'],
                'sort_order' => 0,
            ])->save();
        }

        $this->command?->info("StorySeeder: seeded {$store->stories()->count()} stories for {$store->slug}.");
    }
}
