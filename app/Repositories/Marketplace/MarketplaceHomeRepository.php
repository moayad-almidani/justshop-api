<?php

declare(strict_types=1);

namespace App\Repositories\Marketplace;

use App\Enums\Store\StoreStatusEnum;
use App\Models\Category;
use App\Models\Product;
use App\Models\Marketplace\PromotionalBanner;
use App\Models\Store;
use App\Models\Story\Story;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read-only cross-store queries for the public marketplace home page.
 *
 * This is an intentional exception to merchant CRUD store scoping: the
 * marketplace home aggregates public data across all operational stores.
 * Every result is still constrained to operational stores and public/active
 * catalog records. Do not reuse this repository for merchant administration.
 */
final class MarketplaceHomeRepository
{
    public function stores(int $limit): Collection
    {
        return $this->operationalStores()
            ->with([
                'mediaLinks' => fn ($query) => $query->where('role', 'logo')->with('media'),
            ])
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByDesc('active_products_count')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Stories are grouped by their owning Store. A store only appears when it
     * has at least one currently published, non-expired story.
     */
    public function storyGroups(int $limit): Collection
    {
        $liveStories = static fn ($query) => $query->currentlyPublished();

        return $this->operationalStores()
            ->whereHas('stories', $liveStories)
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->with([
                'mediaLinks' => fn ($query) => $query->where('role', 'logo')->with('media'),
                'stories' => fn ($query) => $query
                    ->currentlyPublished()
                    ->with(['mediaAttachment.media', 'product.translations'])
                    ->orderBy('published_at')
                    ->orderBy('id'),
            ])
            ->withMax(['stories as latest_story_at' => $liveStories], 'published_at')
            ->orderByDesc('latest_story_at')
            ->limit($limit)
            ->get();
    }

    /** Public, currently scheduled promotional content. Ownership/campaign rules are intentionally deferred. */
    public function promotionalBanners(int $limit): Collection
    {
        return PromotionalBanner::query()
            ->visibleNow()
            ->with(['media', 'translations'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function storiesForStore(string $storeSlug): Store
    {
        $store = $this->operationalStores()
            ->where('slug', $storeSlug)
            ->withCount([
                'products as active_products_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->with([
                'mediaLinks' => fn ($query) => $query->where('role', 'logo')->with('media'),
                'stories' => fn ($query) => $query
                    ->currentlyPublished()
                    ->with(['mediaAttachment.media', 'product.translations'])
                    ->orderBy('published_at')
                    ->orderBy('id'),
            ])
            ->first();

        abort_if($store === null, 404, 'Store not found.');

        return $store;
    }

    public function categories(int $limit): Collection
    {
        $baseQuery = Category::query()
            ->root()
            ->active()
            ->with(['translations', 'mediaLinks.media']);

        // Marketplace taxonomy should be global (store_id IS NULL). The current
        // demo seeder still creates tenant-owned roots, so use those only as a
        // compatibility fallback until the taxonomy is migrated to global rows.
        $global = (clone $baseQuery)
            ->whereNull('store_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($global->isNotEmpty()) {
            return $global;
        }

        $operationalStoreIds = $this->operationalStores()->select('id')->pluck('id');
        if ($operationalStoreIds->isEmpty()) {
            return collect();
        }

        return $baseQuery
            ->whereIn('store_id', $operationalStoreIds)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    public function featuredProducts(int $limit): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('is_featured', true)
            ->whereHas('store', fn ($query) => $this->applyOperationalStoreConstraint($query))
            ->whereHas('variants', fn (Builder $query) => $this->applyAvailableVariantConstraint($query))
            ->with([
                'translations',
                'store.mediaLinks' => fn ($query) => $query->where('role', 'logo')->with('media'),
                'category.translations',
                'category.mediaLinks.media',
                'mediaLinks.media',
                'images', // legacy bridge while old catalog records are migrated
                'variants' => fn ($query) => $this->applyAvailableVariantConstraint($query)
                    ->with(['mediaLinks.media', 'images'])
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get();
    }

    private function operationalStores(): Builder
    {
        return Store::query()->where(function ($query) {
            return $this->applyOperationalStoreConstraint($query);
        });
    }

    private function applyOperationalStoreConstraint($query)
    {
        return $query
            ->where('is_active', true)
            ->where('status', StoreStatusEnum::ACTIVE->value);
    }

    private function applyAvailableVariantConstraint($query)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($stock) {
                return $stock->where('track_inventory', false)
                    ->orWhere('quantity', '>', 0);
            });
    }
}
