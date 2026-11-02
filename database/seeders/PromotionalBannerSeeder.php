<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Marketplace\PromotionalBannerStatusEnum;
use App\Enums\Media\MediaType;
use App\Models\Marketplace\PromotionalBanner;
use App\Models\Media\Media;
use Database\Seeders\Concerns\SeedsStaticMedia;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Seeds platform-neutral sample banners; campaign ownership rules are intentionally deferred. */
class PromotionalBannerSeeder extends Seeder
{
    use SeedsStaticMedia;

    public function run(): void
    {
        $banners = [
            [
                'asset' => 'marketplace/banners/banner-sale.svg',
                'path' => 'marketplace/banners/demo-sale.svg',
                'target_url' => '/products?featured=true',
                'sort_order' => 10,
                'translations' => [
                    'ar' => [
                        'title' => 'خصومات تصل إلى 70%',
                        'subtitle' => 'على منتجات مختارة من متاجرنا',
                        'button_text' => 'تسوق الآن',
                    ],
                    'en' => [
                        'title' => 'Up to 70% off',
                        'subtitle' => 'On selected products from our stores',
                        'button_text' => 'Shop now',
                    ],
                ],
            ],
            [
                'asset' => 'marketplace/banners/banner-new-season.svg',
                'path' => 'marketplace/banners/demo-new-season.svg',
                'target_url' => '/products?sort=newest',
                'sort_order' => 20,
                'translations' => [
                    'ar' => [
                        'title' => 'وصلت تشكيلتنا الجديدة',
                        'subtitle' => 'اكتشف أحدث المنتجات من متاجرنا',
                        'button_text' => 'اكتشف الآن',
                    ],
                    'en' => [
                        'title' => 'The latest collection is here',
                        'subtitle' => 'Discover new arrivals from our stores',
                        'button_text' => 'Discover now',
                    ],
                ],
            ],
        ];

        foreach ($banners as $definition) {
            $this->seedStaticAsset($definition['asset'], $definition['path'], 'public');
            $bytes = Storage::disk('public')->get($definition['path']);

            $media = Media::withTrashed()->firstOrNew([
                'disk' => 'public',
                'path' => $definition['path'],
            ]);
            if ($media->exists && $media->trashed()) {
                $media->restore();
            }
            $media->fill([
                'owner_store_id' => null,
                'type' => MediaType::Image,
                'mime_type' => 'image/svg+xml',
                'size_bytes' => strlen($bytes),
                'width' => 1600,
                'height' => 650,
                'duration_sec' => null,
                'checksum' => hash('sha256', $bytes),
            ])->save();

            $banner = PromotionalBanner::withTrashed()->firstOrNew([
                'image_media_id' => $media->id,
            ]);
            if ($banner->exists && $banner->trashed()) {
                $banner->restore();
            }
            $banner->fill([
                'status' => PromotionalBannerStatusEnum::PUBLISHED,
                'target_url' => $definition['target_url'],
                'sort_order' => $definition['sort_order'],
                'starts_at' => null,
                'ends_at' => null,
            ])->save();

            foreach ($definition['translations'] as $locale => $translation) {
                $banner->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'title' => $translation['title'],
                        'subtitle' => $translation['subtitle'] ?? null,
                        'button_text' => $translation['button_text'] ?? null,
                    ],
                );
            }
        }

        $this->command?->info('PromotionalBannerSeeder: seeded ' . count($banners) . ' active sample banners.');
    }
}
