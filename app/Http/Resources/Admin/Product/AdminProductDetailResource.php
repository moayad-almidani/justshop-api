<?php

namespace App\Http\Resources\Admin\Product;

use App\Enums\Product\ProductStatusEnum;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $defaultVariant = $this->resolveDefaultVariant();
        $totalStock     = $this->resolveTotalStock();

        return [
            'id'                => $this->id,
            'store_id'          => $this->store_id,
            'available_locales' => $this->resolveAvailableLocales(),
            'default_locale'    => config('content.default_locale'),
            'translations'      => $this->buildTranslations($request),
            'status'            => $this->is_active
                ? ProductStatusEnum::ACTIVE->value
                : ProductStatusEnum::DRAFT->value,
            'is_featured'       => (bool) $this->is_featured,
            'is_new'            => (bool) $this->is_new,
            'price'             => $defaultVariant
                ? (float) $defaultVariant->price
                : 0,
            'compare_at_price'  => $defaultVariant?->compare_at_price
                ? (float) $defaultVariant->compare_at_price
                : null,
            'cost_per_item'     => $defaultVariant?->cost_price
                ? (float) $defaultVariant->cost_price
                : null,
            'sku'               => $defaultVariant?->sku,
            'barcode'           => $defaultVariant?->barcode,
            'quantity'          => $totalStock,
            'track_quantity'    => true,
            'weight'            => $defaultVariant?->weight,
            'weight_unit'       => $defaultVariant?->weight_unit,

            // ── Dual-layer media ───────────────────────────────
            // Product-level shared gallery (owned by Product directly).
            // Variant-level media lives inside each variant object.
            'media'             => $this->buildProductMedia(),

            'options'           => $this->buildOptions(),
            'variants'          => $this->buildVariants(),

            // ── Taxonomy ───────────────────────────────────────
            'category_id'       => $this->category_id,
            'brand_id'          => $this->brand_id,

            // Tags are store-scoped entities with translated name/slug.
            // editorRelations() eager-loads tags.translations to prevent N+1.
            'tags'              => $this->buildTags(),

            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        ];
    }

    // ── Private Helpers ────────────────────────────────────────

    private function resolveDefaultVariant(): ?ProductVariant
    {
        if (!$this->relationLoaded('variants')) {
            return null;
        }

        return $this->variants
            ->where('is_active', true)
            ->sortBy('price')
            ->first()
            ?? $this->variants->first();
    }

    private function resolveTotalStock(): int
    {
        if (!$this->relationLoaded('variants')) {
            return 0;
        }

        return (int) $this->variants->sum('quantity');
    }

    private function resolveAvailableLocales(): array
    {
        $configuredLocales = config(
            'content.editable_locales',
            config('app.supported_locales', [])
        );

        $translationLocales = $this->relationLoaded('translations')
            ? $this->translations->pluck('locale')->all()
            : [];

        return collect($configuredLocales)
            ->merge($translationLocales)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function buildTranslations(Request $request): array
    {
        $translations = $this->relationLoaded('translations')
            ? $this->translations->keyBy('locale')
            : collect();

        return collect($this->resolveAvailableLocales())
            ->mapWithKeys(function (string $locale) use ($request, $translations): array {
                $translation = $translations->get($locale, ['locale' => $locale]);

                return [
                    $locale => (new AdminProductTranslationResource($translation))
                        ->toArray($request),
                ];
            })
            ->all();
    }

    /**
     * Build product-level shared media.
     *
     * Reads images owned by the Product model directly
     * (imageable_type = App\Models\Product).
     *
     * Maps DB sort_order → API position.
     */
    private function buildProductMedia(): array
    {
        if ($this->relationLoaded('mediaLinks') && $this->mediaLinks->contains(fn ($link) => $link->media !== null)) {
            return \App\Support\Media\CentralGallery::format($this->mediaLinks)->all();
        }
        
        if (!$this->relationLoaded('images')) {
            return [];
        }
        
        return $this->images
            ->sortBy('sort_order')
            ->map(fn($image) => [
                'id'         => $image->id,
                'url'        => $image->full_url,
                'alt'        => $image->alt_text ?? null,
                'position'   => $image->sort_order ?? 0,
                'is_primary' => (bool) $image->is_primary,
            ])
            ->values()
            ->all();
    }

    /**
     * Build canonical product options from the new option system.
     */
    private function buildOptions(): array
    {
        if (!$this->relationLoaded('productOptions')) {
            return [];
        }

        return $this->productOptions
            ->map(fn($option) => [
                'id'       => $option->id,
                'name'     => $option->name,
                'position' => $option->position,
                'values'   => $option->relationLoaded('values')
                    ? $option->values->map(fn($v) => [
                        'id'    => $v->id,
                        'value' => $v->value,
                    ])->values()
                    : [],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Build tags array with translated name and slug.
     *
     * Tags are store-scoped entities. name and slug are NOT columns on the
     * tags table — they live in tag_translations. Reading them directly from
     * the tag model would return null (no such column) or trigger N+1 queries.
     *
     * This method reads from the pre-loaded translations collection.
     * editorRelations() must include 'tags.translations' for this to work
     * without N+1. The Tag::translation() accessor guards against unloaded
     * relations by returning null rather than firing a query.
     *
     * Locale resolution: uses the current application locale, with fallback
     * to the first available translation. Consistent with Product::translation()
     * and Attribute::translation() patterns in this codebase.
     *
     * No direct reads from tags.name or tags.slug anywhere in this method.
     */
    private function buildTags(): array
    {
        if (!$this->relationLoaded('tags')) {
            return [];
        }

        $locale = app()->getLocale();

        return $this->tags
            ->map(function ($tag) use ($locale) {
                // Resolve translation for current locale with fallback.
                // Tag::translation() returns null if translations not loaded —
                // safe here because editorRelations loads tags.translations.
                $translation = $tag->relationLoaded('translations')
                    ? ($tag->translations->where('locale', $locale)->first()
                        ?? $tag->translations->first())
                    : null;

                return [
                    'id'        => $tag->id,
                    'name'      => $translation?->name,
                    'slug'      => $translation?->slug,
                    'color'     => $tag->color,
                    'type'      => $tag->type,
                    'is_active' => (bool) $tag->is_active,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Build variant list.
     *
     * Each variant includes options (new system) and media.
     */
    private function buildVariants(): array
    {
        if (!$this->relationLoaded('variants')) {
            return [];
        }

        return $this->variants
            ->map(fn($variant) => $this->formatVariant($variant))
            ->values()
            ->all();
    }

    private function formatVariant(ProductVariant $variant): array
    {
        // ── New option system ──────────────────────────────────
        $options = $variant->relationLoaded('optionValues')
            ? $this->buildVariantOptions($variant->optionValues)
            : [];

        // ── Variant-level media ────────────────────────────────
        // Owned by ProductVariant. Maps DB sort_order → API position.
        $media = $variant->relationLoaded('mediaLinks') && $variant->mediaLinks->contains(fn ($link) => $link->media !== null)
            ? \App\Support\Media\CentralGallery::format($variant->mediaLinks)->all()
            : null;
        $media ??= $variant->relationLoaded('images')
            ? $variant->images
                ->sortBy('sort_order')
                ->map(fn($image) => [
                    'id'         => $image->id,
                    'url'        => $image->full_url,
                    'alt'        => $image->alt_text ?? null,
                    'position'   => $image->sort_order ?? 0,
                    'is_primary' => (bool) $image->is_primary,
                ])
                ->values()
                ->toArray()
            : [];

        return [
            'id'                  => $variant->id,
            'sku'                 => $variant->sku,
            'barcode'             => $variant->barcode,
            'price'               => (float) $variant->price,
            'compare_at_price'    => $variant->compare_at_price
                ? (float) $variant->compare_at_price
                : null,
            'cost_price'          => $variant->cost_price
                ? (float) $variant->cost_price
                : null,
            'quantity'            => $variant->quantity,
            'low_stock_threshold' => $variant->low_stock_threshold,
            'track_inventory'     => (bool) $variant->track_inventory,
            'weight'              => $variant->weight,
            'weight_unit'         => $variant->weight_unit,
            'manufacture_date'    => $variant->manufacture_date,
            'expiry_date'         => $variant->expiry_date,
            'batch_number'        => $variant->batch_number,
            'is_active'           => (bool) $variant->is_active,
            'options'             => $options,
            'media'               => $media,
        ];
    }

    private function buildVariantOptions($optionValues): array
    {
        return $optionValues
            ->map(fn($ov) => [
                'option_id'    => $ov->option_id,
                'option_name'  => $ov->relationLoaded('option')
                    ? $ov->option?->name
                    : null,
                'value_id'     => $ov->id,
                'value'        => $ov->value,
            ])
            ->values()
            ->toArray();
    }
}