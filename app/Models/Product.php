<?php

namespace App\Models;

use App\Models\Concerns\HasStoreScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Media\ProductMedia;
use App\Models\Story\Story;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, HasStoreScoping;

    protected $fillable = [
        'store_id',
        'category_id',
        'brand_id',
        'product_variant_id',
        'is_active',
        'is_featured',
        'is_new',
        'sort_order',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'is_new'      => 'boolean',
        'sort_order'  => 'integer',
    ];

    /** Centralized attachment table; legacy images()/assets() retained during migration. */
    public function mediaLinks(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    // ── Relationships ──────────────────────────────────────────

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class, 'product_id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function defaultVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Canonical product options (new system).
     * Ordered by position ascending.
     */
    public function productOptions(): HasMany
    {
        return $this->hasMany(ProductOption::class, 'product_id')
            ->orderBy('position');
    }

    /**
     * Product-level shared media gallery.
     * Ordered by sort_order ascending.
     * Owned directly by the Product (imageable_type = App\Models\Product).
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')
            ->orderBy('sort_order');
    }

    public function tags(): BelongsToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** Stories may optionally tag a product from the same store. */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->where('is_approved', true);
    }

    // ── Primary Variant Resolution ─────────────────────────────

    /**
     * Get the primary/default variant for this product.
     * Returns explicitly set default, or first active variant, or any variant.
     */
    public function primaryVariant(): ?ProductVariant
    {
        if ($this->product_variant_id
            && $this->relationLoaded('defaultVariant')
            && $this->defaultVariant
        ) {
            return $this->defaultVariant;
        }

        if ($this->relationLoaded('activeVariants')) {
            $active = $this->activeVariants->first();
            if ($active) {
                return $active;
            }
        }

        if ($this->relationLoaded('variants')) {
            return $this->variants->first();
        }

        return null;
    }

    // ── Accessors ──────────────────────────────────────────────

    /**
     * @deprecated Use primaryVariant()->sku instead.
     */
    public function getSkuAttribute(): ?string
    {
        return $this->primaryVariant()?->sku;
    }

    public function getDisplayVariantAttribute(): ?ProductVariant
    {
        return $this->primaryVariant();
    }

    /**
     * Resolve the primary image URL for this product.
     *
     * Priority order (dual-layer media architecture):
     *   1. Product-level images (imageable = Product) — correct owner post-refactor.
     *   2. Default variant images — backward-compatible fallback for products
     *      that were created before the dual-layer media refactor, where all
     *      images were assigned to the first variant.
     *
     * Both branches guard against N+1 by checking relationLoaded() first.
     * If neither relation is loaded, returns null rather than firing a query.
     */
    public function getPrimaryImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('mediaLinks') && $this->mediaLinks->isNotEmpty()) {
            $selected = $this->mediaLinks->first(fn ($link) => $link->is_main && $link->media?->type === \App\Enums\Media\MediaType::Image)
                ?? $this->mediaLinks->first(fn ($link) => $link->media?->type === \App\Enums\Media\MediaType::Image);
            if ($selected) {
                return $selected->media?->url();
            }
        }

        // ── 1. Product-level images (correct owner) ────────────
        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            $primary = $this->images->where('is_primary', true)->first()
                ?? $this->images->first();

            return $primary?->full_url;
        }

        // ── 2. Variant-level fallback (bridge period) ──────────
        // Covers products created before the media refactor where images
        // were attached to the first variant instead of the product.
        $variant = $this->primaryVariant();

        if (!$variant) {
            return null;
        }

        if ($variant->relationLoaded('images')) {
            $primary = $variant->images->where('is_primary', true)->first()
                ?? $variant->images->first();

            return $primary?->full_url;
        }

        return null;
    }

    public function getAvgRatingAttribute(): ?float
    {
        if ($this->relationLoaded('approvedReviews')) {
            $avg = $this->approvedReviews->avg('rating');
            return $avg ? round((float) $avg, 1) : null;
        }

        return round((float) $this->approvedReviews()->avg('rating'), 1) ?: null;
    }

    public function getReviewsCountAttribute(): int
    {
        if ($this->relationLoaded('approvedReviews')) {
            return $this->approvedReviews->count();
        }

        return $this->approvedReviews()->count();
    }

    // ── Translation Helpers ────────────────────────────────────

    public function translation(?string $locale = null): ?ProductTranslation
    {
        $locale = $locale ?? app()->getLocale();

        return $this->translations->where('locale', $locale)->first()
            ?? $this->translations->first();
    }

    public function translated(string $key, ?string $locale = null): ?string
    {
        return $this->translation($locale)?->{$key};
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where($query->getModel()->getTable() . '.is_active', true);
    }

    public function scopeFindBySlug(
        \Illuminate\Database\Eloquent\Builder $query,
        string $slug,
        ?string $locale = null,
    ): \Illuminate\Database\Eloquent\Builder {
        $locale = $locale ?? app()->getLocale();

        return $query->where(function ($q) use ($slug, $locale) {
            $q->whereHas('translations', function ($t) use ($slug, $locale) {
                $t->where('slug', $slug)->where('locale', $locale);
            })->orWhereHas('translations', function ($t) use ($slug) {
                $t->where('slug', $slug);
            });
        });
    }

    public static function findBySlugOrFail(
        string $slug,
        ?string $locale = null,
    ): self {
        $product = static::query()->findBySlug($slug, $locale)->first();

        if (!$product) {
            throw new \App\Exceptions\Product\ProductNotFoundException();
        }

        return $product;
    }
}
