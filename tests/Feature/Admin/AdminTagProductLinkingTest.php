<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Actions\Admin\Product\CreateProductAction;
use App\DTOs\Admin\Product\CreateProductDTO;
use App\Models\Store;
use App\Models\Tag;
use App\Repositories\Admin\Tag\AdminTagRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Covers "Tag ↔ Product linking" — how products reference tags by ID
 * (App\Actions\Admin\Product\CreateProductAction::execute()) and how tag
 * store-scope is validated (App\Repositories\Admin\Tag\AdminTagRepository::
 * findInaccessibleTagIds()), both real classes used by
 * App\Http\Controllers\Api\Merchant\AdminProductController::store().
 *
 * These are exercised directly (unit-style) rather than through the full
 * HTTP product-creation endpoint: creating a product for real requires a
 * large, mostly-unrelated payload (variants, options, canonical option
 * mapping, an active StoreEntitlementSnapshot, etc. — see
 * CreateProductRequest::rules()) that belongs to the Product domain, which
 * is out of this audit's scope. Calling CreateProductAction::execute()
 * directly still exercises the exact real code path the HTTP endpoint
 * calls (App\Actions\Admin\Product\CreateProductAction::validateTagStoreScope()
 * -> AdminTagRepository::findInaccessibleTagIds() ->
 * AdminProductRepository::syncTags() -> Product::tags()), which is the
 * genuine "linking to products" behavior asked for.
 */
class AdminTagProductLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function minimalProductDto(Store $store, array $tagIds): CreateProductDTO
    {
        return new CreateProductDTO(
            storeId: $store->id,
            categoryId: \App\Models\Category::factory()->create(['store_id' => $store->id])->id,
            brandId: null,
            isActive: true,
            isFeatured: false,
            translations: [
                ['locale' => 'en', 'name' => 'Linked Product', 'slug' => 'linked-product-' . uniqid()],
            ],
            options: [],
            variants: [
                ['sku' => 'SKU-' . uniqid(), 'price' => 10, 'quantity' => 1, 'is_active' => true, 'options' => []],
            ],
            media: [],
            tags: $tagIds,
            isNew: true,
        );
    }

    public function test_creating_a_product_links_it_to_store_owned_and_global_tags(): void
    {
        $store = Store::factory()->create();
        app()->instance('storeId', $store->id);
        app()->instance('currentStore', $store);

        $storeTag = Tag::factory()->create(['store_id' => $store->id]);
        $globalTag = Tag::factory()->create(['store_id' => null]);

        $dto = $this->minimalProductDto($store, [$storeTag->id, $globalTag->id]);

        $product = app(CreateProductAction::class)->execute($dto);

        $this->assertTrue($product->is_new);
        $this->assertCount(2, $product->tags);
        $this->assertEqualsCanonicalizing(
            [$storeTag->id, $globalTag->id],
            $product->tags->pluck('id')->all(),
        );
    }

    public function test_creating_a_product_with_a_tag_from_another_store_is_rejected(): void
    {
        $store = Store::factory()->create();
        app()->instance('storeId', $store->id);
        app()->instance('currentStore', $store);

        $otherStore = Store::factory()->create();
        $foreignTag = Tag::factory()->create(['store_id' => $otherStore->id]);

        $dto = $this->minimalProductDto($store, [$foreignTag->id]);

        $this->expectException(ValidationException::class);

        app(CreateProductAction::class)->execute($dto);
    }

    public function test_find_inaccessible_tag_ids_reports_only_the_ids_outside_store_scope(): void
    {
        $store = Store::factory()->create();
        app()->instance('storeId', $store->id);

        $storeTag = Tag::factory()->create(['store_id' => $store->id]);
        $globalTag = Tag::factory()->create(['store_id' => null]);
        $otherStore = Store::factory()->create();
        $foreignTag = Tag::factory()->create(['store_id' => $otherStore->id]);

        $invalidIds = app(AdminTagRepository::class)->findInaccessibleTagIds(
            [$storeTag->id, $globalTag->id, $foreignTag->id],
            $store->id,
        );

        $this->assertSame([$foreignTag->id], array_values($invalidIds));
    }

    public function test_find_inaccessible_tag_ids_returns_empty_array_for_empty_input(): void
    {
        $store = Store::factory()->create();
        app()->instance('storeId', $store->id);

        $invalidIds = app(AdminTagRepository::class)->findInaccessibleTagIds([], $store->id);

        $this->assertSame([], $invalidIds);
    }
}
