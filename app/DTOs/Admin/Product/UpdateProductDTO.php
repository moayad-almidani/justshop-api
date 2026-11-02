<?php

namespace App\DTOs\Admin\Product;

use App\Http\Requests\Admin\Product\UpdateProductRequest;

class UpdateProductDTO
{
    /**
     * @param  int        $storeId
     * @param  int        $productId
     * @param  int|null   $categoryId
     * @param  int|null   $brandId
     * @param  bool|null  $isActive
     * @param  bool|null  $isFeatured
     * @param  bool|null  $isNew
     * @param  array|null $translations  Locale-keyed translation data arrays.
     * @param  array|null $options       Canonical product option definitions.
     * @param  array|null $variants      Variant data arrays (each may include media[]).
     * @param  array|null $media         Product-level shared gallery media items.
     *                                   null = no change. [] = clear all media.
     * @param  int[]|null $tags          Tag IDs to sync. null = no change.
     *                                   [] = detach all tags.
     *                                   Tags are store-scoped entities managed
     *                                   via the dedicated tag API. Product
     *                                   endpoints accept IDs only.
     * @param  bool|null  $syncVariants
     */
    public function __construct(
        public int     $storeId,
        public int     $productId,
        public ?int    $categoryId    = null,
        public ?int    $brandId       = null,
        public ?bool   $isActive      = null,
        public ?bool   $isFeatured    = null,
        public ?array  $translations  = null,
        public ?array  $options       = null,
        public ?array  $variants      = null,
        public ?array  $media         = null,
        public ?array  $tags          = null,
        public ?bool   $syncVariants  = null,
        public ?bool   $isNew         = null,
    ) {}

    public static function fromRequest(
        UpdateProductRequest $request,
        int $storeId,
        int $productId,
    ): self {
        // Tags: null when key absent (no change), int[] when present.
        $tags = null;

        if ($request->exists('tags')) {
            $raw  = $request->input('tags');
            $tags = is_array($raw)
                ? array_map('intval', $raw)
                : [];
        }

        return new self(
            storeId:       $storeId,
            productId:     $productId,
            categoryId:    self::nullableInteger($request, 'category_id'),
            brandId:       self::nullableInteger($request, 'brand_id'),
            isActive:      self::optionalBoolean($request, 'is_active'),
            isFeatured:    self::optionalBoolean($request, 'is_featured'),
            translations:  $request->input('translations'),
            options:       $request->input('options'),
            variants:      $request->input('variants'),
            media:         $request->input('media'),
            tags:          $tags,
            syncVariants:  self::optionalBoolean($request, 'sync_variants'),
            isNew:         self::optionalBoolean($request, 'is_new'),
        );
    }

    private static function nullableInteger(
        UpdateProductRequest $request,
        string $key,
    ): ?int {
        if (!$request->exists($key)) {
            return null;
        }

        $value = $request->input($key);

        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function optionalBoolean(
        UpdateProductRequest $request,
        string $key,
    ): ?bool {
        if (!$request->exists($key)) {
            return null;
        }

        return $request->boolean($key);
    }
}
