<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStoreScoping;
use App\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Media\CategoryMedia;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes, HasStoreScoping;

    protected $fillable = [
        'store_id',
        'slug',
        'parent_id',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /** Centralized attachment table; legacy images()/assets() retained during migration. */
    public function mediaLinks(): HasMany
    {
        return $this->hasMany(CategoryMedia::class)->orderBy('sort_order');
    }

    // ── Relationships ──────────────────────────────────────────

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(CategoryTranslation::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parents(): BelongsTo
    {
        return $this->parent()->with('parents');
    }

    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    // ── Translation Helpers ────────────────────────────────────

    public function translation(?string $locale = null): ?CategoryTranslation
    {
        $locale = $locale ?? app()->getLocale();

        return $this->translations->where('locale', $locale)->first()
            ?? $this->translations->first();
    }

    public function translated(string $key, ?string $locale = null): ?string
    {
        return $this->translation($locale)?->{$key};
    }

    // ── Static Finders ─────────────────────────────────────────

    public static function findByLocalizedSlug(
        string $slug,
        int $storeId,
        ?string $locale = null,
    ): ?self {
        $locale = $locale ?? app()->getLocale();

        $translation = CategoryTranslation::where('slug', $slug)
            ->where('locale', $locale)
            ->first();

        if (!$translation) {
            $translation = CategoryTranslation::where('slug', $slug)->first();
        }

        if (!$translation) {
            return static::where('store_id', $storeId)
                ->where('slug', $slug)
                ->first();
        }

        return static::where('store_id', $storeId)
            ->find($translation->category_id);
    }

    public static function findByLocalizedSlugOrFail(
        string $slug,
        int $storeId,
        ?string $locale = null,
    ): self {
        $category = static::findByLocalizedSlug($slug, $storeId, $locale);

        if (!$category) {
            throw new NotFoundException('Category not found.');
        }

        return $category;
    }

    // ── Descendant Helpers ─────────────────────────────────────

    public function allDescendantIds(): array
    {
        $ids = [$this->id];
        $this->loadMissing('descendants');
        $this->collectDescendantIds($this, $ids);

        return $ids;
    }

    private function collectDescendantIds(Category $category, array &$ids): void
    {
        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $this->collectDescendantIds($child, $ids);
        }
    }

    // ── Breadcrumb ─────────────────────────────────────────────

    public function getBreadcrumbAttribute(): \Illuminate\Support\Collection
    {
        $locale     = app()->getLocale();
        $breadcrumb = collect();
        $current    = $this;

        $current->loadMissing('parents.translations');

        while ($current) {
            $translation = $current->translation($locale);

            $breadcrumb->prepend([
                'id'   => $current->id,
                'name' => $translation?->name ?? $current->slug,
                'slug' => $translation?->slug ?? $current->slug,
            ]);

            $current = $current->parent;
        }

        return $breadcrumb;
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
