<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Models\Store;
use Database\Seeders\PromotionalBannerSeeder;
use Database\Seeders\StorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceDemoSeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_story_seeder_populates_the_first_demo_store_story_relationship(): void
    {
        $store = Store::factory()->create(['slug' => 'merchant-store']);

        $this->seed(StorySeeder::class);

        $this->assertGreaterThanOrEqual(3, $store->fresh()->stories()->count());
        $this->assertDatabaseCount('story_media', 3);
        $this->assertSame(3, Store::query()->whereKey($store->id)->firstOrFail()->stories->count());
        $this->assertSame(3, Store::query()->orderBy('id')->firstOrFail()->stories->count());

        $this->getJson('/api/v1/public/marketplace/home?locale=ar')
            ->assertOk()
            ->assertJsonCount(1, 'data.stories')
            ->assertJsonPath('data.stories.0.story_count', 3);

        foreach (['new-arrivals', 'special-offer', 'best-sellers'] as $key) {
            Storage::disk('public')->assertExists("stores/{$store->id}/stories/demo-{$key}.svg");
        }
    }

    public function test_database_seeders_are_repeatable_for_stories_and_banners(): void
    {
        Store::factory()->create(['slug' => 'merchant-store']);

        $this->seed(StorySeeder::class);
        $this->seed(PromotionalBannerSeeder::class);
        $this->seed(StorySeeder::class);
        $this->seed(PromotionalBannerSeeder::class);

        $this->assertDatabaseCount('stories', 3);
        $this->assertDatabaseCount('story_media', 3);
        $this->assertDatabaseCount('promotional_banners', 2);
        $this->assertDatabaseCount('promotional_banner_translations', 4);
        $this->assertDatabaseCount('media', 5);
    }
}
