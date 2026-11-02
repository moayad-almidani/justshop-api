<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\Enums\Media\MediaType;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\Media\Media;
use App\Models\Product;
use App\Models\ProductTranslation;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\Story\Story;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MarketplaceHomeApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_home_returns_stores_root_categories_featured_products_and_live_store_stories(): void
    {
        $store = Store::factory()->create([
            'name' => 'Venom Demo Store',
            'slug' => 'venom-demo-store',
            'is_active' => true,
            'status' => 'active',
        ]);

        $storeLogo = $this->media($store, 'stores/logo.png');
        $store->mediaLinks()->create([
            'media_id' => $storeLogo->id,
            'role' => 'logo',
            'sort_order' => 0,
        ]);

        $rootCategory = Category::query()->create([
            'store_id' => null,
            'parent_id' => null,
            'slug' => 'electronics',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $rootCategory->id,
            'locale' => 'en',
            'name' => 'Electronics',
            'slug' => 'electronics-en',
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $rootCategory->id,
            'locale' => 'ar',
            'name' => 'الإلكترونيات',
            'slug' => 'electronics-ar',
        ]);

        $childCategory = Category::query()->create([
            'store_id' => null,
            'parent_id' => $rootCategory->id,
            'slug' => 'phones',
            'is_active' => true,
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $childCategory->id,
            'locale' => 'en',
            'name' => 'Phones',
            'slug' => 'phones-en',
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'category_id' => $rootCategory->id,
            'is_active' => true,
            'is_featured' => true,
            'is_new' => true,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'en',
            'name' => 'Featured Phone',
            'slug' => 'featured-phone',
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'FEATURED-PHONE-001',
            'price' => 199.99,
            'compare_at_price' => 249.99,
            'quantity' => 5,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $productImage = $this->media($store, 'products/featured-phone.png');
        $product->mediaLinks()->create([
            'media_id' => $productImage->id,
            'is_main' => true,
            'sort_order' => 0,
        ]);

        $storyMedia = $this->media($store, 'stories/story-one.png');
        $story = Story::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'status' => StoryStatusEnum::PUBLISHED,
            'caption' => 'New phone arrived',
            'published_at' => now()->subMinutes(10),
            'expires_at' => now()->addHours(23),
        ]);
        $story->mediaAttachment()->create([
            'media_id' => $storyMedia->id,
            'alt_text' => 'New phone',
            'sort_order' => 0,
        ]);

        $expiredMedia = $this->media($store, 'stories/expired.png');
        $expiredStory = Story::query()->create([
            'store_id' => $store->id,
            'status' => StoryStatusEnum::PUBLISHED,
            'published_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
        ]);
        $expiredStory->mediaAttachment()->create(['media_id' => $expiredMedia->id]);

        $response = $this->getJson('/api/v1/public/marketplace/home?locale=en');

        $response->assertOk()
            ->assertJsonPath('data.stores.0.slug', 'venom-demo-store')
            ->assertJsonPath('data.stores.0.logo_url', $storeLogo->url())
            ->assertJsonPath('data.stores.0.currency', 'USD')
            ->assertJsonPath('data.stores.0.active_products_count', 1)
            ->assertJsonCount(1, 'data.stories')
            ->assertJsonPath('data.stories.0.store.slug', 'venom-demo-store')
            ->assertJsonPath('data.stories.0.store.logo_url', $storeLogo->url())
            ->assertJsonMissingPath('data.stories.0.store.currency')
            ->assertJsonMissingPath('data.stories.0.store.active_products_count')
            ->assertJsonPath('data.stories.0.story_count', 1)
            ->assertJsonCount(1, 'data.stories.0.stories')
            ->assertJsonPath('data.stories.0.stories.0.caption', 'New phone arrived')
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.name', 'Electronics')
            ->assertJsonCount(1, 'data.featured_products')
            ->assertJsonPath('data.featured_products.0.name', 'Featured Phone')
            ->assertJsonPath('data.featured_products.0.price', 199.99)
            ->assertJsonPath('data.featured_products.0.compare_at_price', 249.99)
            ->assertJsonPath('data.featured_products.0.is_new', true)
            ->assertJsonPath('data.featured_products.0.discount_percentage', 20)
            ->assertJsonPath('data.featured_products.0.image_url', $productImage->url());

        $this->getJson('/api/v1/public/marketplace/stores?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'venom-demo-store')
            ->assertJsonPath('data.0.currency', 'USD')
            ->assertJsonPath('data.0.active_products_count', 1);

        $this->getJson('/api/v1/public/marketplace/stories?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.store.slug', 'venom-demo-store')
            ->assertJsonPath('data.0.store.logo_url', $storeLogo->url())
            ->assertJsonMissingPath('data.0.store.currency')
            ->assertJsonMissingPath('data.0.store.active_products_count')
            ->assertJsonPath('data.0.story_count', 1)
            ->assertJsonCount(1, 'data.0.stories');

        $this->getJson('/api/v1/public/marketplace/stores/venom-demo-store/stories?locale=en')
            ->assertOk()
            ->assertJsonPath('data.store.slug', 'venom-demo-store')
            ->assertJsonMissingPath('data.store.currency')
            ->assertJsonMissingPath('data.store.active_products_count')
            ->assertJsonCount(1, 'data.stories');
    }

    public function test_home_uses_tenant_root_categories_as_compatibility_fallback_when_no_global_roots_exist(): void
    {
        $store = Store::factory()->create(['status' => 'active', 'is_active' => true]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'parent_id' => null,
            'slug' => 'store-root-category',
            'is_active' => true,
        ]);
        CategoryTranslation::query()->create([
            'category_id' => $category->id,
            'locale' => 'en',
            'name' => 'Store Root Category',
            'slug' => 'store-root-category-en',
        ]);

        $response = $this->getJson('/api/v1/public/marketplace/categories?locale=en');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Store Root Category');
    }

    public function test_featured_product_returns_no_discount_when_compare_at_price_is_not_higher(): void
    {
        $store = Store::factory()->create([
            'is_active' => true,
            'status' => 'active',
        ]);

        $category = Category::query()->create([
            'store_id' => null,
            'parent_id' => null,
            'slug' => 'regular-category',
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'is_active' => true,
            'is_featured' => true,
        ]);

        ProductTranslation::query()->create([
            'product_id' => $product->id,
            'locale' => 'en',
            'name' => 'Regular Featured Product',
            'slug' => 'regular-featured-product',
        ]);

        ProductVariant::query()->create([
            'product_id' => $product->id,
            'sku' => 'REGULAR-FEATURED-001',
            'price' => 100,
            'compare_at_price' => 90,
            'quantity' => 4,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/public/marketplace/featured-products?locale=en')
            ->assertOk()
            ->assertJsonPath('data.0.is_new', false)
            ->assertJsonPath('data.0.discount_percentage', null);
    }

    private function media(Store $store, string $path): Media
    {
        Storage::disk('public')->put($path, 'fake-media-bytes');

        return Media::query()->create([
            'owner_store_id' => $store->id,
            'type' => MediaType::Image,
            'disk' => 'public',
            'path' => $path,
            'mime_type' => 'image/png',
            'size_bytes' => 16,
            'width' => 1,
            'height' => 1,
            'checksum' => hash('sha256', $path),
        ]);
    }
}
