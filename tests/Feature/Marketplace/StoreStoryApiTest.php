<?php

declare(strict_types=1);

namespace Tests\Feature\Marketplace;

use App\DTOs\Story\CreateStoreStoryDTO;
use App\Enums\Media\MediaType;
use App\Enums\Story\StoryStatusEnum;
use App\Models\Media\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\Story\Story;
use App\Actions\Story\CreateStoreStoryAction;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreStoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_story_upload_is_owned_by_store_and_expires_after_24_hours(): void
    {
        $store = Store::factory()->create();
        $file = $this->png();

        $story = app(CreateStoreStoryAction::class)->execute(new CreateStoreStoryDTO(
            storeId: $store->id,
            file: $file,
            caption: 'Today offer',
            productId: null,
            altText: 'Offer image',
        ));

        $this->assertSame($store->id, $story->store_id);
        $this->assertSame(StoryStatusEnum::PUBLISHED, $story->status);
        $this->assertEqualsWithDelta(24 * 60 * 60, $story->published_at->diffInSeconds($story->expires_at), 3);
        $this->assertDatabaseHas('media', [
            'id' => $story->mediaAttachment->media_id,
            'owner_store_id' => $store->id,
            'type' => MediaType::Image->value,
        ]);
        Storage::disk('public')->assertExists($story->mediaAttachment->media->path);
    }

    public function test_story_cannot_tag_a_product_from_another_store(): void
    {
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $category = \App\Models\Category::query()->create([
            'store_id' => $otherStore->id,
            'slug' => 'other-story-category',
        ]);
        $otherProduct = Product::query()->create([
            'store_id' => $otherStore->id,
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        try {
            app(CreateStoreStoryAction::class)->execute(new CreateStoreStoryDTO(
                storeId: $store->id,
                file: $this->png(),
                caption: null,
                productId: $otherProduct->id,
                altText: null,
            ));
            $this->fail('Cross-store product tagging should not be allowed.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('stories', 0);
            $this->assertDatabaseCount('media', 0);
        }
    }

    private function png(): UploadedFile
    {
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent('story.png', $bytes);
    }
}
