<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Merchant;

use App\Actions\Admin\Category\CreateCategoryAction;
use App\Actions\Admin\Category\DeleteCategoryAction;
use App\Actions\Admin\Category\ListCategoriesAction;
use App\Actions\Admin\Category\RestoreCategoryAction;
use App\Actions\Admin\Category\ShowCategoryAction;
use App\Actions\Admin\Category\UpdateCategoryAction;
use App\DTOs\Admin\Category\CreateCategoryDTO;
use App\DTOs\Admin\Category\DeleteCategoryDTO;
use App\DTOs\Admin\Category\ListCategoriesDTO;
use App\DTOs\Admin\Category\RestoreCategoryDTO;
use App\DTOs\Admin\Category\ShowCategoryDTO;
use App\DTOs\Admin\Category\UpdateCategoryDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Category\CreateCategoryRequest;
use App\Http\Requests\Admin\Category\ListCategoriesRequest;
use App\Http\Requests\Admin\Category\UpdateCategoryRequest;
use App\Http\Resources\Admin\Category\AdminCategoryResource;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Http\JsonResponse;

class AdminCategoryController extends Controller
{
    public function index(
        ListCategoriesRequest $request,
        Store $store,
        ListCategoriesAction $action,
    ): JsonResponse {
        $this->authorize('viewAny', [Category::class, $store]);

        $categories = $action->execute(
            dto:  ListCategoriesDTO::fromRequest($request, $store->id),
        );

        return $this->paginated(
            $categories,
            AdminCategoryResource::collection($categories),
        );
    }

    public function show(
        Store $store,
        int $category,
        ShowCategoryAction $action,
    ): JsonResponse {
        $this->authorize('view', [Category::class, $store]);

        $result = $action->execute(
            dto: new ShowCategoryDTO(
                storeId:    $store->id,
                categoryId: $category,
            ),
        );

        return $this->success(new AdminCategoryResource($result->loadMissing('mediaLinks.media')));
    }

    public function store(
        CreateCategoryRequest $request,
        Store $store,
        CreateCategoryAction $action,
    ): JsonResponse {
        $this->authorize('create', [Category::class, $store]);

        $result = $action->execute(
            dto:  CreateCategoryDTO::fromRequest($request, $store->id),
        );

        return $this->success(
            data:       new AdminCategoryResource($result->loadMissing('mediaLinks.media')),
            message:    __( 'category.created'),
            statusCode: 201,
        );
    }

    public function update(
        UpdateCategoryRequest $request,
        Store $store,
        int $category,
        UpdateCategoryAction $action,
    ): JsonResponse {
        $this->authorize('update', [Category::class, $store]);

        $result = $action->execute(
            dto:  UpdateCategoryDTO::fromRequest($request, $store->id, $category),
        );

        return $this->success(
            data:    new AdminCategoryResource($result->loadMissing('mediaLinks.media')),
            message: __( 'category.updated'),
        );
    }

    public function destroy(
        Store $store,
        int $category,
        DeleteCategoryAction $action,
    ): JsonResponse {
        $this->authorize('delete', [Category::class, $store]);

        $action->execute(
            dto: new DeleteCategoryDTO(
                storeId:    $store->id,
                categoryId: $category,
            ),
        );

        return $this->success(
            message: __( 'category.deleted'),
        );
    }

    public function restore(
        Store $store,
        int $category,
        RestoreCategoryAction $action,
    ): JsonResponse {
        $this->authorize('restore', [Category::class, $store]);

        $result = $action->execute(
            dto: new RestoreCategoryDTO(
                storeId:    $store->id,
                categoryId: $category,
            ),
        );

        return $this->success(
            data:    new AdminCategoryResource($result->loadMissing('mediaLinks.media')),
            message: __( 'category.restored'),
        );
    }
}
