<?php

namespace App\DTOs\Admin\Product;

use App\Enums\Product\ProductStatusEnum;
use App\Http\Requests\Admin\Product\CreateProductRequest;

class CreateProductDTO
{
    /**
     * @param  int    $storeId
     * @param  int|null $categoryId
     * @param  int|null $brandId
     * @param  bool   $isActive
     * @param  bool   $isFeatured
     * @param  array  $translations   Locale-keyed translation data arrays.
     * @param  array  $options        Canonical product option definitions.
     * @param  array  $variants       Variant data arrays (each may include media[]).
     * @param  array  $media          Product-level shared gallery media items.
     * @param  int[]  $tags           Tag IDs to sync. Tags are store-scoped entities
     *                                managed via the dedicated tag API. Product
     *                                endpoints accept IDs only.
     * @param  bool   $isNew       Merchant-controlled New badge.
     */
    public function __construct(
        public int    $storeId,
        public ?int   $categoryId,
        public ?int   $brandId,
        public bool   $isActive,
        public bool   $isFeatured,
        public array  $translations,
        public array  $options,
        public array  $variants,
        public array  $media,
        public array  $tags = [],
        public bool   $isNew = false,
    ) {}

    public static function fromRequest(
        CreateProductRequest $request,
        int $storeId,
    ): self {
        return new self(
            storeId:      $storeId,
            categoryId:   self::nullableInteger($request, 'category_id'),
            brandId:      self::nullableInteger($request, 'brand_id'),
            isActive:     $request->input('status') === ProductStatusEnum::ACTIVE->value,
            isFeatured:   $request->boolean('is_featured', false),
            isNew:        $request->boolean('is_new', false),
            translations: $request->input('translations', []),
            options:      $request->input('options', []),
            variants:     $request->input('variants', []),
            media:        $request->input('media', []),
            // Tags arrive as validated integer IDs after request validation.
            tags:         array_map('intval', $request->input('tags', [])),
        );
    }

    private static function nullableInteger(
        CreateProductRequest $request,
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
}