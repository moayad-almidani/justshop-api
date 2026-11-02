<?php

namespace App\Actions\Admin\Product;

use App\DTOs\Admin\Product\CreateProductDTO;
use App\Models\Product;
use App\Models\StoreEntitlementSnapshot;
use App\Enums\Entitlement\FeatureKeyEnum;
use App\Exceptions\Entitlement\QuotaExceededException;
use App\Repositories\Admin\Product\AdminProductRepository;
use App\Repositories\Admin\Tag\AdminTagRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateProductAction
{
    public function __construct(
        private AdminProductRepository $repository,
        private AdminTagRepository     $tagRepository,
    ) {}

    public function execute(CreateProductDTO $dto): Product
    {
        // ── Phase C: Store-scope tag validation ────────────────
        // Runs before the transaction. Ensures every provided tag ID is
        // accessible to this store (owned by store OR global/null store_id).
        // FormRequest only verifies exists:tags,id — not store ownership.
        $this->validateTagStoreScope($dto->tags, $dto->storeId);

        return DB::transaction(function () use ($dto) {

            // ── 0. Atomic quota guard, inside same transaction ──────────
            // lockForUpdate() here serializes any concurrent product creation
            // requests for the same store on this row specifically, same pattern
            // as CreateStoreAction.
            $snapshot = StoreEntitlementSnapshot::where('store_id', $dto->storeId)
                ->lockForUpdate()
                ->first();

            if ($snapshot) {
                $limit = $snapshot->features[FeatureKeyEnum::PRODUCTS_MAX->value] ?? null;

                if ($limit !== null && $snapshot->products_count >= $limit) {
                    throw new QuotaExceededException(
                        featureKey: FeatureKeyEnum::PRODUCTS_MAX->value,
                        limit: $limit,
                    );
                }
            }
            // $snapshot === null means no active subscription — this should be
            // blocked earlier (via FeatureGateService::ensureWriteAccess in
            // middleware/policy). Don't duplicate that check here, only check the counter.
            // ─────────────────────────────────────────────────────────

            // ── 1. Create the product record ───────────────────
            $product = $this->repository->create([
                'store_id'    => $dto->storeId,
                'category_id' => $dto->categoryId,
                'brand_id'    => $dto->brandId,
                'is_active'   => $dto->isActive,
                'is_featured' => $dto->isFeatured,
                'is_new'      => $dto->isNew,
            ]);

            // ── 2. Create translations ─────────────────────────
            foreach ($dto->translations as $translation) {
                $this->repository->createTranslation($product, $translation);
            }

            // ── 3. Sync canonical product options ──────────────
            $productOptionMap = [];

            if (!empty($dto->options)) {
                $productOptionMap = $this->repository->syncProductOptions(
                    $product,
                    $dto->options,
                );
            }

            // ── 4. Create variants ─────────────────────────────
            $productNameForSku = 'product';
            if (!empty($dto->translations)) {
                foreach ($dto->translations as $translation) {
                    if (!empty($translation['name'])) {
                        $productNameForSku = $translation['name'];
                        break;
                    }
                }
            }

            foreach ($dto->variants as $variantData) {

                $sku = $variantData['sku'] ?? null;
                if (empty($sku)) {
                    $sku = 'AUTO-' . strtoupper(Str::slug($productNameForSku, '-')) . '-' . now()->timestamp . '-' . Str::random(4);
                }

                $variant = $this->repository->createVariant($product, [
                    'sku'                 => $sku,
                    'barcode'             => $variantData['barcode'] ?? null,
                    'price'               => $variantData['price'],
                    'compare_at_price'    => $variantData['compare_at_price'] ?? null,
                    'cost_price'          => $variantData['cost_price'] ?? null,
                    'quantity'            => $variantData['quantity'],
                    'low_stock_threshold' => $variantData['low_stock_threshold'] ?? 5,
                    'track_inventory'     => $variantData['track_inventory'] ?? true,
                    'is_active'           => $variantData['is_active'] ?? true,
                    'weight'              => $variantData['weight'] ?? null,
                    'weight_unit'         => $variantData['weight_unit'] ?? null,
                    'manufacture_date'    => $variantData['manufacture_date'] ?? null,
                    'expiry_date'         => $variantData['expiry_date'] ?? null,
                    'batch_number'        => $variantData['batch_number'] ?? null,
                ]);

                // Sync semantic option values
                if (!empty($variantData['options']) && !empty($productOptionMap)) {
                    $this->repository->syncVariantOptionValues(
                        $variant,
                        $variantData['options'],
                        $productOptionMap,
                    );
                }

                // Variant-level media
                if (!empty($variantData['media'])) {
                    $this->repository->createVariantMedia(
                        $variant,
                        $variantData['media'],
                    );
                }
            }

            // ── 5. Sync tags ───────────────────────────────────
            // Tag IDs already store-scope validated above.
            if (!empty($dto->tags)) {
                $this->repository->syncTags($product, $dto->tags);
            }

            // ── 6. Set default variant (first created) ─────────
            $product->refresh();
            $firstVariant = $product->variants()->first();

            if ($firstVariant) {
                $this->repository->update($product, [
                    'product_variant_id' => $firstVariant->id,
                ]);
            }

            // ── 7. Create product-level media ──────────────────
            if (!empty($dto->media)) {
                $this->repository->createProductMedia($product, $dto->media);
            }

            // ── 8. Return fully loaded product ─────────────────
            return $this->repository->refreshEditorProduct($product);
        });
    }

    /**
     * Validate that all provided tag IDs are accessible to this store.
     *
     * A tag is accessible if:
     *   - Its store_id matches $storeId (store-owned tag), OR
     *   - Its store_id is null (global/system tag).
     *
     * Tags belonging to a different store are rejected with a structured
     * ValidationException so the frontend receives per-field error information.
     *
     * @param  int[]  $tagIds
     * @throws ValidationException
     */
    private function validateTagStoreScope(array $tagIds, int $storeId): void
    {
        if (empty($tagIds)) {
            return;
        }

        $invalidIds = $this->tagRepository->findInaccessibleTagIds($tagIds, $storeId);

        if (!empty($invalidIds)) {
            throw ValidationException::withMessages([
                'tags' => __('product.tags_not_accessible_to_store', [
                    'ids' => implode(', ', $invalidIds),
                ]),
            ]);
        }
    }
}