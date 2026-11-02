<?php

namespace App\Repositories\Admin\Product;

use App\Enums\Product\ProductStatusEnum;
use App\Exceptions\Product\ProductNotFoundException;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\ProductVariant;
use App\Support\Media\MediaUrl;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AdminProductRepository
{
    // ── Eager-load Definitions ─────────────────────────────────

    /**
     * Relations for the admin product editor response.
     *
     * ── Media (dual-layer architecture) ───────────────────────
     * 'images'          → Product-level shared gallery.
     *                     imageable_type = App\Models\Product.
     * 'variants.images' → Variant-level specific media.
     *                     imageable_type = App\Models\ProductVariant.
     *
     * ── Option system (new) ────────────────────────────────────
     * 'productOptions.values'        → Canonical option definitions.
     * 'variants.optionValues.option' → Per-variant option value assignments
     *                                  via variant_option_values pivot.
     *
     * ── Attribute system (legacy — bridge period) ─────────────
     * Removed in Phase 8.
     * Storefront now uses the new option system.
     *
     * ── Tags ──────────────────────────────────────────────────
     * 'tags.translations' → Tag metadata + all locale translations.
     *                       Required by AdminProductDetailResource::buildTags().
     *                       name and slug live in tag_translations, NOT tags.
     *                       Loading translations here prevents N+1 in resource.
     *
     * ── Common ────────────────────────────────────────────────
     * 'category', 'translations'
     */
    private function editorRelations(): array
    {
        return [
            // ── Product-level media ────────────────────────────
            'images',
            'mediaLinks.media',

            // ── New option system ──────────────────────────────
            'productOptions.values',
            'variants.optionValues.option',

            // ── Variant-level media ────────────────────────────
            'variants.images',
            'variants.mediaLinks.media',

            // ── Common ─────────────────────────────────────────
            'category',
            'translations',

            // ── Tags with translations ─────────────────────────
            // name and slug live in tag_translations, not tags table.
            // Must load translations here to avoid N+1 in buildTags().
            'tags.translations',
        ];
    }

    // ── Read Operations ────────────────────────────────────────

    public function listForStore(
        int $storeId,
        ?string $search = null,
        ?string $status = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        $query = Product::query()
            ->where('store_id', $storeId);

        if ($search) {
            $query->whereHas('translations', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%");
            });
        }

        if ($status === ProductStatusEnum::ACTIVE->value) {
            $query->where('is_active', true);
        } elseif ($status === ProductStatusEnum::INACTIVE->value) {
            $query->where('is_active', false);
        }

        // List view loads a lighter relation set than the editor.
        // Product-level images included so getPrimaryImageUrlAttribute
        // resolves without N+1.
        // Tags not included in list view — not needed for listing UI.
        return $query
            ->with([
                'category',
                'images',
                'mediaLinks.media',
                'variants',
                'variants.images',
                'variants.mediaLinks.media',
                'translations',
            ])
            ->latest()
            ->paginate($perPage);
    }

    public function findInStore(int $productId, int $storeId): Product
    {
        $product = Product::query()
            ->where('store_id', $storeId)
            ->where('id', $productId)
            ->first();

        if (!$product) {
            throw new ProductNotFoundException();
        }

        return $product;
    }

    public function findEditorProductInStore(int $productId, int $storeId): Product
    {
        $product = Product::query()
            ->with($this->editorRelations())
            ->where('store_id', $storeId)
            ->where('id', $productId)
            ->first();

        if (!$product) {
            throw new ProductNotFoundException();
        }

        return $product;
    }

    public function findTrashedInStore(int $productId, int $storeId): ?Product
    {
        return Product::withTrashed()
            ->where('store_id', $storeId)
            ->where('id', $productId)
            ->first();
    }

    /**
     * Reload a product with all admin editor relations after a write operation.
     */
    public function refreshEditorProduct(Product $product): Product
    {
        return $product->fresh($this->editorRelations())
            ?? $product->load($this->editorRelations());
    }

    // ── Product Write Operations ───────────────────────────────

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);
        return $product->fresh();
    }

    public function softDelete(Product $product): void
    {
        $product->delete();
    }

    public function restore(Product $product): Product
    {
        $product->restore();
        return $product->fresh();
    }

    // ── Translation Operations ─────────────────────────────────

    public function createTranslation(Product $product, array $translationData): void
    {
        $product->translations()->create($translationData);
    }

    public function upsertTranslation(
        Product $product,
        string $locale,
        array $translationData,
    ): void {
        $product->translations()->updateOrCreate(
            ['locale' => $locale],
            $translationData,
        );
    }

    public function deleteTranslations(Product $product): void
    {
        $product->translations()->delete();
    }

    // ── Tag Operations ─────────────────────────────────────────

    /**
     * Sync tag associations for a product.
     *
     * Accepts an array of integer tag IDs.
     * Laravel's sync() detaches removed IDs and attaches new ones atomically.
     * Passing an empty array detaches all tags from the product.
     *
     * Store-scope validation: the caller (Action layer) is responsible for
     * ensuring all tag IDs belong to the correct store. Request validation
     * confirms existence in the tags table via exists:tags,id.
     *
     * @param  int[]  $tagIds
     */
    public function syncTags(Product $product, array $tagIds): void
    {
        $product->tags()->sync($tagIds);
    }

    // ── Variant Operations ─────────────────────────────────────

    public function createVariant(Product $product, array $variantData): ProductVariant
    {
        return $product->variants()->create($variantData);
    }

    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $variant->update($data);
        return $variant->refresh();
    }

    public function deleteVariant(ProductVariant $variant): void
    {
        $variant->delete();
    }

    // ── Media Operations ───────────────────────────────────────

    /**
     * Create product-level media items.
     *
     * Attaches images directly to the Product (imageable = Product).
     * These represent the shared product gallery.
     *
     * Maps API `position` → DB `sort_order`.
     * First item receives is_primary = true.
     *
     * ⚠️ CRITICAL: Before marking the first item as primary, we must
     * unset any existing is_primary flag to prevent duplicate primaries.
     *
     * @param  array  $mediaItems  [['url' => '...', 'alt' => '...', 'position' => 0], ...]
     */
    public function createProductMedia(Product $product, array $mediaItems): void
    {
        $hasPrimaryInBatch = !empty($mediaItems);

        if ($hasPrimaryInBatch) {
            // Clear any existing primary flag before setting a new one.
            // This prevents multiple is_primary=true rows from accumulating.
            $product->images()->where('is_primary', true)->update(['is_primary' => false]);
        }

        foreach ($mediaItems as $index => $mediaData) {
            $product->images()->create([
                'image_url'  => $this->normalizeImagePath($mediaData['url']),
                'alt_text'   => $mediaData['alt'] ?? null,
                'sort_order' => $mediaData['position'] ?? $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    /**
     * Create variant-level media items.
     *
     * Attaches images directly to the ProductVariant (imageable = ProductVariant).
     * These represent variant-specific visuals.
     *
     * Maps API `position` → DB `sort_order`.
     * First item receives is_primary = true.
     *
     * ⚠️ CRITICAL: Before marking the first item as primary, we must
     * unset any existing is_primary flag to prevent duplicate primaries.
     *
     * @param  array  $mediaItems  [['url' => '...', 'alt' => '...', 'position' => 0], ...]
     */
    public function createVariantMedia(ProductVariant $variant, array $mediaItems): void
    {
        $hasPrimaryInBatch = !empty($mediaItems);

        if ($hasPrimaryInBatch) {
            // Clear any existing primary flag before setting a new one.
            // This prevents multiple is_primary=true rows from accumulating.
            $variant->images()->where('is_primary', true)->update(['is_primary' => false]);
        }

        foreach ($mediaItems as $index => $mediaData) {
            $variant->images()->create([
                'image_url'  => $this->normalizeImagePath($mediaData['url']),
                'alt_text'   => $mediaData['alt'] ?? null,
                'sort_order' => $mediaData['position'] ?? $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    /**
     * Normalize image path by stripping domain and /storage/ prefix.
     * Ensures we always store relative paths in the database.
     *
     * Examples:
     * - "http://localhost:8000/storage/variants/xyz.jpg" → "variants/xyz.jpg"
     * - "/storage/variants/xyz.jpg" → "variants/xyz.jpg"
     * - "variants/xyz.jpg" → "variants/xyz.jpg"
     * - "https://example.com/image.jpg" → "https://example.com/image.jpg" (external URL preserved)
     */
    private function normalizeImagePath(string $url): string
    {
        return (string) MediaUrl::normalizeStorablePath($url);
    }

    /**
     * Replace all product-level media.
     *
     * Deletes existing product images then recreates from the given list.
     * Used by UpdateProductAction for full sync replacement.
     * Passing an empty array clears all product media.
     *
     * @param  array  $mediaItems  [['url' => '...', 'alt' => '...', 'position' => 0], ...]
     */
    public function syncProductMedia(Product $product, array $mediaItems): void
    {
        $product->images()->delete();

        if (!empty($mediaItems)) {
            $this->createProductMedia($product, $mediaItems);
        }
    }

    /**
     * Replace all variant-level media.
     *
     * Deletes existing variant images then recreates from the given list.
     * Used by UpdateProductAction for full sync replacement.
     * Passing an empty array clears all variant media.
     *
     * @param  array  $mediaItems  [['url' => '...', 'alt' => '...', 'position' => 0], ...]
     */
    public function syncVariantMedia(ProductVariant $variant, array $mediaItems): void
    {
        $variant->images()->delete();

        if (!empty($mediaItems)) {
            $this->createVariantMedia($variant, $mediaItems);
        }
    }

    // ── New Option System ──────────────────────────────────────

    /**
     * Sync canonical product options (new system).
     *
     * Creates or updates each option by name, syncs its allowed values,
     * and removes options/values that are no longer in the list.
     *
     * Returns a map of option name → ProductOption (with values loaded).
     *
     * @param  array  $options  [['name' => 'Color', 'position' => 1, 'values' => ['Red', 'Blue']], ...]
     * @return array<string, ProductOption>
     */
    public function syncProductOptions(Product $product, array $options): array
    {
        $optionMap     = [];
        $incomingNames = array_column($options, 'name');

        foreach ($options as $optionData) {
            $option = $product->productOptions()->updateOrCreate(
                ['name' => $optionData['name']],
                ['position' => $optionData['position']],
            );

            $incomingValues = $optionData['values'] ?? [];

            foreach ($incomingValues as $value) {
                $option->values()->firstOrCreate(['value' => $value]);
            }

            $option->values()
                ->whereNotIn('value', $incomingValues)
                ->delete();

            $optionMap[$optionData['name']] = $option->load('values');
        }

        $product->productOptions()
            ->whereNotIn('name', $incomingNames)
            ->delete();

        return $optionMap;
    }

    /**
     * Sync variant option value assignments (new system).
     *
     * @param  array<string, string>        $optionsMap
     * @param  array<string, ProductOption> $productOptionMap
     */
    public function syncVariantOptionValues(
        ProductVariant $variant,
        array $optionsMap,
        array $productOptionMap,
    ): void {
        $syncData = [];

        foreach ($optionsMap as $optionName => $value) {
            $option = $productOptionMap[$optionName] ?? null;

            if (!$option) {
                continue;
            }

            $optionValue = $option->values->firstWhere('value', $value);

            if (!$optionValue) {
                continue;
            }

            $syncData[$optionValue->id] = ['option_id' => $option->id];
        }

        $variant->optionValues()->sync($syncData);
    }


}
