<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Enums\Marketplace\PromotionalBannerStatusEnum;
use App\Enums\Media\MediaType;
use App\Models\Marketplace\PromotionalBanner;
use App\Models\Media\Media;
use Database\Seeders\PromotionalBannerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotionalBannerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_banner_localized_copy_uses_the_project_translation_table_convention(): void
    {
        $this->assertTrue(Schema::hasTable('promotional_banner_translations'));
        $this->assertFalse(Schema::hasColumn('promotional_banners', 'title'));
        $this->assertFalse(Schema::hasColumn('promotional_banners', 'subtitle'));
        $this->assertFalse(Schema::hasColumn('promotional_banners', 'button_text'));
    }

    public function test_public_endpoint_returns_only_published_banners_inside_their_schedule(): void
    {
        $active = $this->banner('active.svg', PromotionalBannerStatusEnum::PUBLISHED, now()->subHour(), now()->addDay());
        $active->translations()->create([
            'locale' => 'ar',
            'title' => 'خصومات تصل إلى 70%',
            'subtitle' => 'على منتجات مختارة',
            'button_text' => 'تسوق الآن',
        ]);
        $active->translations()->create([
            'locale' => 'en',
            'title' => 'Up to 70% off',
            'subtitle' => 'Selected products',
            'button_text' => 'Shop now',
        ]);

        $draft = $this->banner('draft.svg', PromotionalBannerStatusEnum::DRAFT, null, null);
        $draft->translations()->create(['locale' => 'ar', 'title' => 'مسودة']);

        $expired = $this->banner('expired.svg', PromotionalBannerStatusEnum::PUBLISHED, null, now()->subMinute());
        $expired->translations()->create(['locale' => 'ar', 'title' => 'منتهي']);

        $response = $this->getJson('/api/v1/public/marketplace/promotional-banners?locale=ar');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id)
            ->assertJsonPath('data.0.title', 'خصومات تصل إلى 70%')
            ->assertJsonPath('data.0.button_text', 'تسوق الآن')
            ->assertJsonPath('data.0.target_url', '/products?featured=true');

        $this->getJson('/api/v1/public/marketplace/promotional-banners?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Up to 70% off')
            ->assertJsonPath('data.0.button_text', 'Shop now');
    }

    public function test_promotional_banner_seeder_populates_the_home_api(): void
    {
        $this->seed(PromotionalBannerSeeder::class);

        $this->assertDatabaseCount('promotional_banners', 2);
        $this->assertDatabaseCount('promotional_banner_translations', 4);

        $this->getJson('/api/v1/public/marketplace/home?locale=ar')
            ->assertOk()
            ->assertJsonCount(2, 'data.promotional_banners')
            ->assertJsonPath('data.promotional_banners.0.title', 'خصومات تصل إلى 70%')
            ->assertJsonPath('data.promotional_banners.0.button_text', 'تسوق الآن');
    }

    private function banner(
        string $path,
        PromotionalBannerStatusEnum $status,
        mixed $startsAt,
        mixed $endsAt,
    ): PromotionalBanner {
        Storage::disk('public')->put("marketplace/test/{$path}", 'fixture');
        $media = Media::query()->create([
            'owner_store_id' => null,
            'type' => MediaType::Image,
            'disk' => 'public',
            'path' => "marketplace/test/{$path}",
            'mime_type' => 'image/svg+xml',
            'size_bytes' => 7,
            'width' => 1,
            'height' => 1,
            'checksum' => hash('sha256', $path),
        ]);

        return PromotionalBanner::query()->create([
            'image_media_id' => $media->id,
            'status' => $status,
            'target_url' => '/products?featured=true',
            'sort_order' => 0,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
    }
}
