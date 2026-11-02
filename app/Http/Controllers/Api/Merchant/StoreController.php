<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Merchant;

use App\Http\Controllers\Controller;
use App\Exceptions\Store\UnauthorizedStoreAccessException;
use App\Http\Requests\Store\CreateStoreRequest;
use App\Http\Requests\Store\UpdateStoreRequest;
use App\Http\Requests\Store\ValidateSlugRequest;
use App\Http\Resources\Store\StoreResource;
use App\Actions\Store\CreateStoreAction;
use App\Actions\Store\UpdateStoreAction;
use App\DTOs\Store\CreateStoreDTO;
use App\DTOs\Store\UpdateStoreDTO;
use App\Models\Store;
use App\Services\Store\StoreSlugService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StoreController extends Controller
{
    public function __construct(
        private CreateStoreAction $createStoreAction,
        private UpdateStoreAction $updateStoreAction,
        private StoreSlugService $slugService,
    ) {}

    public function validateSlug(ValidateSlugRequest $request): JsonResponse
    {
        $this->authorize('create', Store::class);

        $slug = $this->slugService->normalize($request->validated('slug'));
        $isAvailable = $this->slugService->isAvailable($slug);

        return $this->success([
            'slug' => $slug,
            'is_available' => $isAvailable,
            'is_reserved' => $this->slugService->isReserved($slug),
        ], 'Slug validation completed');
    }

    public function create(CreateStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Store::class);

        $dto = CreateStoreDTO::fromRequest($request);
        $store = $this->createStoreAction->execute($dto);

        return $this->success(new StoreResource($store->loadMissing('mediaLinks.media')), 'Store created successfully', 201);
    }

    public function show(Request $request, Store $store): JsonResponse
    {
        $storeModel = app('currentStore');
        $this->guardStoreAccess($request, $storeModel);
        $this->authorize('view', $storeModel);

        return $this->success(new StoreResource($storeModel->loadMissing('mediaLinks.media')), 'Store retrieved successfully');
    }

    public function update(UpdateStoreRequest $request, Store $store): JsonResponse
    {
        $storeModel = app('currentStore');
        $this->guardStoreAccess($request, $storeModel);
        $this->authorize('update', $storeModel);

        $dto = UpdateStoreDTO::fromRequest($request, $store->id);
        $storeModel = $this->updateStoreAction->execute($dto);

        return $this->success(new StoreResource($storeModel->loadMissing('mediaLinks.media')), 'Store updated successfully');
    }

    private function guardStoreAccess(Request $request, Store $store): void
    {
        $user = $request->user();

        if ($user === null) {
            throw new UnauthorizedStoreAccessException();
        }

        $hasAccess = $store->owner_id === $user->id
            || $user->stores()->whereKey($store->id)->exists();

        if (!$hasAccess) {
            throw new UnauthorizedStoreAccessException();
        }
    }
}
