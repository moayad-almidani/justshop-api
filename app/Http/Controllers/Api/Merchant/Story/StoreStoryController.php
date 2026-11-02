<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Merchant\Story;

use App\Actions\Story\CreateStoreStoryAction;
use App\Actions\Story\DeleteStoreStoryAction;
use App\DTOs\Story\CreateStoreStoryDTO;
use App\DTOs\Story\DeleteStoreStoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\Story\CreateStoreStoryRequest;
use App\Http\Resources\Marketplace\MarketplaceStoryResource;
use App\Models\Store;
use App\Repositories\Story\StoreStoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreStoryController extends Controller
{
    public function index(Request $request, Store $store, StoreStoryRepository $repository): JsonResponse
    {
        $this->authorize('view', $store);

        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $stories = $repository->paginateForStore($store->id, $perPage);
        $data = $stories->getCollection()
            ->map(fn ($story) => (new MarketplaceStoryResource($story))->resolve($request))
            ->values()
            ->all();

        return $this->paginated($stories, $data);
    }

    public function store(
        CreateStoreStoryRequest $request,
        Store $store,
        CreateStoreStoryAction $action,
    ): JsonResponse {
        $this->authorize('update', $store);

        $story = $action->execute(CreateStoreStoryDTO::fromRequest($request, $store->id));

        return $this->success(
            (new MarketplaceStoryResource($story))->resolve($request),
            'Story published',
            201,
        );
    }

    public function destroy(
        Store $store,
        int $storyId,
        DeleteStoreStoryAction $action,
    ): JsonResponse {
        $this->authorize('update', $store);

        $action->execute(new DeleteStoreStoryDTO($store->id, $storyId));

        return $this->success(null, 'Story deleted');
    }
}
