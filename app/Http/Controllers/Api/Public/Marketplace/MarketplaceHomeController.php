<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public\Marketplace;

use App\Actions\Marketplace\GetMarketplaceHomeAction;
use App\Actions\Marketplace\GetPromotionalBannersAction;
use App\DTOs\Marketplace\GetMarketplaceHomeDTO;
use App\DTOs\Marketplace\GetPromotionalBannersDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Marketplace\GetMarketplaceHomeRequest;
use App\Http\Requests\Public\Marketplace\GetPromotionalBannersRequest;
use App\Http\Resources\Marketplace\MarketplaceCategoryResource;
use App\Http\Resources\Marketplace\MarketplaceFeaturedProductResource;
use App\Http\Resources\Marketplace\MarketplaceStoreResource;
use App\Http\Resources\Marketplace\MarketplacePromotionalBannerResource;
use App\Http\Resources\Marketplace\MarketplaceStoryGroupResource;
use App\Http\Resources\Marketplace\MarketplaceStoryResource;
use App\Repositories\Marketplace\MarketplaceHomeRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MarketplaceHomeController extends Controller
{
    public function show(
        GetMarketplaceHomeRequest $request,
        GetMarketplaceHomeAction $action,
    ): JsonResponse {
        $dto = GetMarketplaceHomeDTO::fromRequest($request);
        $sections = $action->execute($dto);

        return $this->successWithMeta([
            'stores' => $this->resolveCollection(MarketplaceStoreResource::class, $sections['stores'], $request),
            'stories' => $this->resolveCollection(MarketplaceStoryGroupResource::class, $sections['stories'], $request),
            'promotional_banners' => $this->resolveCollection(MarketplacePromotionalBannerResource::class, $sections['promotional_banners'], $request),
            'categories' => $this->resolveCollection(MarketplaceCategoryResource::class, $sections['categories'], $request),
            'featured_products' => $this->resolveCollection(MarketplaceFeaturedProductResource::class, $sections['featured_products'], $request),
        ], [
            'locale' => $dto->locale,
            'generated_at' => now()->toISOString(),
        ], 'Marketplace home loaded');
    }

    public function stores(GetMarketplaceHomeRequest $request, MarketplaceHomeRepository $repository): JsonResponse
    {
        $limit = (int) ($request->validated('stores_limit') ?? 10);
        $items = $repository->stores($limit);

        return $this->success($this->resolveCollection(MarketplaceStoreResource::class, $items, $request));
    }

    public function stories(GetMarketplaceHomeRequest $request, MarketplaceHomeRepository $repository): JsonResponse
    {
        $limit = (int) ($request->validated('stories_limit') ?? 10);
        $items = $repository->storyGroups($limit);

        return $this->success($this->resolveCollection(MarketplaceStoryGroupResource::class, $items, $request));
    }

    public function storeStories(
        string $storeSlug,
        GetMarketplaceHomeRequest $request,
        MarketplaceHomeRepository $repository,
    ): JsonResponse {
        $store = $repository->storiesForStore($storeSlug);
        $resource = (new MarketplaceStoryGroupResource($store))->resolve($request);

        return $this->success($resource);
    }

    public function promotionalBanners(
        GetPromotionalBannersRequest $request,
        GetPromotionalBannersAction $action,
    ): JsonResponse {
        $items = $action->execute(GetPromotionalBannersDTO::fromRequest($request));

        return $this->success($this->resolveCollection(MarketplacePromotionalBannerResource::class, $items, $request));
    }

    public function categories(GetMarketplaceHomeRequest $request, MarketplaceHomeRepository $repository): JsonResponse
    {
        $limit = (int) ($request->validated('categories_limit') ?? 12);
        $items = $repository->categories($limit);

        return $this->success($this->resolveCollection(MarketplaceCategoryResource::class, $items, $request));
    }

    public function featuredProducts(GetMarketplaceHomeRequest $request, MarketplaceHomeRepository $repository): JsonResponse
    {
        $limit = (int) ($request->validated('featured_products_limit') ?? 12);
        $items = $repository->featuredProducts($limit);

        return $this->success($this->resolveCollection(MarketplaceFeaturedProductResource::class, $items, $request));
    }

    /** @param class-string $resourceClass */
    private function resolveCollection(string $resourceClass, Collection $items, Request $request): array
    {
        return $items
            ->map(fn ($item) => (new $resourceClass($item))->resolve($request))
            ->values()
            ->all();
    }
}
