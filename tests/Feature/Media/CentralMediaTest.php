<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Actions\Media\DeleteAttachmentAction;
use App\Actions\Media\SetMainAttachmentAction;
use App\Actions\Media\UploadAttachmentAction;
use App\DTOs\Media\SetMainAttachmentDTO;
use App\DTOs\Media\UploadAttachmentDTO;
use App\Enums\Media\MediaType;
use App\Models\Category;
use App\Models\Media\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Repositories\Media\MediaAttachmentRepository;
use App\Support\Media\CentralGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CentralMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function image(string $name = 'photo.png'): UploadedFile
    {
        // Real one-pixel PNG; unlike UploadedFile::fake()->image this does not require GD.
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg==');
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    private function catalog(): array
    {
        $store = Store::factory()->create();
        $category = Category::query()->create([
            'store_id' => $store->id, 'slug' => 'category-' . $store->id,
        ]);
        $product = Product::query()->create([
            'store_id' => $store->id, 'category_id' => $category->id,
        ]);
        $variant = ProductVariant::query()->create([
            'product_id' => $product->id, 'sku' => 'SKU-' . $store->id, 'price' => 15,
        ]);

        return [$store, $category, $product, $variant];
    }

    private function upload(Store $store, string $entity, int $entityId, bool $main = false): \App\Models\Media\MediaAttachment
    {
        return app(UploadAttachmentAction::class)->execute(new UploadAttachmentDTO(
            $store->id, $entity, $entityId, $this->image(), null, $main, 0,
        ));
    }

    public function test_each_entity_has_a_real_foreign_key_attachment(): void
    {
        [$store, $category, $product, $variant] = $this->catalog();
        $links = [
            $this->upload($store, 'products', $product->id),
            $this->upload($store, 'variants', $variant->id),
            $this->upload($store, 'categories', $category->id),
            $this->upload($store, 'stores', $store->id),
        ];

        $this->assertCount(4, Media::all());
        $this->assertCount(1, $product->mediaLinks()->get());
        $this->assertCount(1, $variant->mediaLinks()->get());
        $this->assertCount(1, $category->mediaLinks()->get());
        $this->assertCount(1, $store->mediaLinks()->get());
        foreach ($links as $link) {
            $this->assertSame($store->id, $link->media->owner_store_id);
            Storage::disk('public')->assertExists($link->media->path);
            $this->assertSame(MediaType::Image, $link->media->type);
        }
    }

    public function test_identical_checksums_still_create_independent_media_and_files(): void
    {
        [$store, $category, $product] = $this->catalog();
        $first = $this->upload($store, 'products', $product->id);
        $second = $this->upload($store, 'categories', $category->id);
        $this->assertNotEquals($first->media_id, $second->media_id);
        $this->assertSame($first->media->checksum, $second->media->checksum);
        $this->assertNotSame($first->media->path, $second->media->path);
    }

    public function test_gallery_contains_variant_then_parent_files(): void
    {
        [$store, , $product, $variant] = $this->catalog();
        $parent = $this->upload($store, 'products', $product->id);
        $specific = $this->upload($store, 'variants', $variant->id);

        $gallery = CentralGallery::forVariant($variant);
        $this->assertSame([$specific->media_id, $parent->media_id], $gallery->pluck('media_id')->all());
    }

    public function test_only_one_main_image_can_exist_and_can_be_reassigned(): void
    {
        [$store, , $product] = $this->catalog();
        $old = $this->upload($store, 'products', $product->id);
        $new = $this->upload($store, 'products', $product->id);
        $this->assertTrue($old->refresh()->is_main);
        $this->assertNull($new->refresh()->is_main);

        app(SetMainAttachmentAction::class)->execute(new SetMainAttachmentDTO(
            $store->id, 'products', $product->id, $new->id,
        ));
        $this->assertNull($old->refresh()->getRawOriginal('is_main'));
        $this->assertTrue($new->refresh()->is_main);
        $this->assertSame(1, $product->mediaLinks()->where('is_main', 1)->count());
    }

    public function test_cross_store_entity_is_not_visible_even_to_attachment_repository(): void
    {
        [$store, , $product] = $this->catalog();
        $other = Store::factory()->create();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(MediaAttachmentRepository::class)->findTarget($other->id, 'products', $product->id);
    }

    public function test_cannot_attach_variant_from_another_store(): void
    {
        [$store, , , $variant] = $this->catalog();
        $other = Store::factory()->create();
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(MediaAttachmentRepository::class)->findTarget($other->id, 'variants', $variant->id);
    }

    public function test_detach_soft_deletes_media_but_retains_storage_until_prune(): void
    {
        [$store, , $product] = $this->catalog();
        $link = $this->upload($store, 'products', $product->id);
        $media = $link->media;
        app(DeleteAttachmentAction::class)->execute($store->id, 'products', $product->id, $link->id);

        $this->assertDatabaseMissing('product_media', ['id' => $link->id]);
        $this->assertSoftDeleted('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);
    }

    public function test_prune_does_not_delete_media_still_referenced_by_any_entity(): void
    {
        [$store, $category, $product] = $this->catalog();
        $linked = $this->upload($store, 'products', $product->id);
        $orphan = $this->upload($store, 'categories', $category->id);
        app(DeleteAttachmentAction::class)->execute($store->id, 'categories', $category->id, $orphan->id);
        DB::table('media')->whereIn('id', [$linked->media_id, $orphan->media_id])->update(['created_at' => now()->subDays(10)]);

        $this->artisan('media:prune', ['--days' => 7])->assertSuccessful();
        $this->assertDatabaseHas('media', ['id' => $linked->media_id]);
        $this->assertDatabaseMissing('media', ['id' => $orphan->media_id]);
        Storage::disk('public')->assertExists($linked->media->path);
        Storage::disk('public')->assertMissing($orphan->media->path);
    }

    public function test_database_cascades_attachment_only_on_hard_delete(): void
    {
        [$store, , $product] = $this->catalog();
        $link = $this->upload($store, 'products', $product->id);
        $product->forceDelete();
        $this->assertDatabaseMissing('product_media', ['id' => $link->id]);
        $this->assertDatabaseHas('media', ['id' => $link->media_id]);
    }
}
