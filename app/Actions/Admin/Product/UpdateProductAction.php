<?php

namespace App\Actions\Admin\Product;

use App\DTOs\Admin\Product\UpdateProductDTO;
use App\Enums\RoleEnum;
use App\Exceptions\Store\UnauthorizedStoreAccessException;
use App\Models\Product;
use App\Repositories\Admin\Product\AdminProductRepository;
use App\Repositories\Admin\Tag\AdminTagRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Str;

class UpdateProductAction
{
    public function __construct(
        private AdminProductRepository $repository,
        private AdminTagRepository     $tagRepository,
    ) {}

    public function execute(UpdateProductDTO $dto): Product
    {
        $product = $this->repository->findInStore($dto->productId, $dto->storeId);

        // ── Phase C: Store-scope tag validation ────────────────
        // Only validate when tags key is present in the request.
        // null = no change (skip validation).
        // [] = detach all (empty array, no IDs to validate, skip).
        if (!is_null($dto->tags) && !empty($dto->tags)) {
            $this->validateTagStoreScope($dto->tags, $dto->storeId);
        }

        return DB::transaction(function () use ($dto, $product) {

            // ── 1. Update product fields ───────────────────────
            $productData = array_filter([
                'category_id' => $dto->categoryId,
                'brand_id'    => $dto->brandId,
                'is_active'   => $dto->isActive,
                'is_featured' => $dto->isFeatured,
                'is_new'      => $dto->isNew,
            ], fn($v) => !is_null($v));

            if (!empty($productData)) {
                $this->repository->update($product, $productData);
            }

            // ── 2. Update translations ─────────────────────────
            if (!is_null($dto->translations)) {
                foreach ($dto->translations as $translation) {
                    $this->repository->upsertTranslation(
                        $product,
                        $translation['locale'],
                        $translation,
                    );
                }
            }

            // ── 3. Sync canonical product options ──────────────
            $productOptionMap = [];

            if (!is_null($dto->options)) {
                $productOptionMap = $this->repository->syncProductOptions(
                    $product,
                    $dto->options,
                );
            } elseif ($dto->syncVariants === true) {
                $product->load('productOptions.values');
                foreach ($product->productOptions as $option) {
                    $productOptionMap[$option->name] = $option;
                }
            }

            // ── 4. Sync variants ───────────────────────────────
            if ($dto->syncVariants === true && !is_null($dto->variants)) {

                $existingVariantIds  = $product->variants()
                    ->pluck('id')
                    ->toArray();

                $processedVariantIds = [];

                foreach ($dto->variants as $variantData) {

                    $sku = $variantData['sku'] ?? null;
                    if (empty($sku)) {
                        $productName = $product->name ?? 'product';
                        $sku = 'AUTO-' . strtoupper(Str::slug($productName, '-')) . '-' . now()->timestamp . '-' . Str::random(4);
                    }

                    $payload = [
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
                    ];

                    if (!empty($variantData['id'])) {
                        $variant = $product->variants()
                            ->where('id', $variantData['id'])
                            ->firstOrFail();

                        $this->repository->updateVariant($variant, $payload);
                    } else {
                        $variant = $this->repository->createVariant($product, $payload);
                    }

                    $processedVariantIds[] = $variant->id;

                    if (isset($variantData['options']) && !empty($productOptionMap)) {
                        $this->repository->syncVariantOptionValues(
                            $variant,
                            $variantData['options'],
                            $productOptionMap,
                        );
                    }

                    // Variant-level media sync.
                    // array_key_exists distinguishes absent (no change)
                    // from null/[] (clear media).
                    if (array_key_exists('media', $variantData)) {
                        $this->repository->syncVariantMedia(
                            $variant,
                            $variantData['media'] ?? [],
                        );
                    }
                }

                // Soft-delete variants removed from the list
                $variantIdsToDelete = array_diff(
                    $existingVariantIds,
                    $processedVariantIds,
                );

                if (!empty($variantIdsToDelete)) {
                    $variantsToDelete = $product->variants()
                        ->whereIn('id', $variantIdsToDelete)
                        ->get();

                    foreach ($variantsToDelete as $variantToDelete) {
                        // Detach option values before soft delete
                        $variantToDelete->optionValues()->detach();
                        $this->repository->deleteVariant($variantToDelete);
                    }
                }

                // Ensure default variant still exists
                $product->refresh();

                $defaultStillExists = $product->variants()
                    ->where('id', $product->product_variant_id)
                    ->exists();

                if (!$defaultStillExists) {
                    $newDefault = $product->variants()->first();
                    $this->repository->update($product, [
                        'product_variant_id' => $newDefault?->id,
                    ]);
                }
            }

            // ── 5. Sync tags ───────────────────────────────────
            // null = key absent = no change to tag associations.
            // [] = explicit empty = detach all tags.
            // [1,2,3] = sync to these IDs (store-scope validated above).
            if (!is_null($dto->tags)) {
                $this->repository->syncTags($product, $dto->tags);
            }

            // ── 6. Sync product-level media ────────────────────
            // null = no change. [] = clear. [...] = replace.
            if (!is_null($dto->media)) {
                $this->repository->syncProductMedia($product, $dto->media);
            }

            // ── 7. Return fully loaded product ─────────────────
            return $this->repository->refreshEditorProduct($product);
        });
    }

    /**
     * Validate that all provided tag IDs are accessible to this store.
     *
     * @param  int[]  $tagIds
     * @throws ValidationException
     */
    private function validateTagStoreScope(array $tagIds, int $storeId): void
    {
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