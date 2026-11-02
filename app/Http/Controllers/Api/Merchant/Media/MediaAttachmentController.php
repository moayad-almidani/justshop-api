<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Merchant\Media;

use App\Actions\Media\DeleteAttachmentAction;
use App\Actions\Media\UploadAttachmentAction;
use App\Actions\Media\SetMainAttachmentAction;
use App\DTOs\Media\SetMainAttachmentDTO;
use App\DTOs\Media\UploadAttachmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Merchant\Media\UploadAttachmentRequest;
use App\Http\Resources\Media\MediaAttachmentResource;
use App\Models\Category;
use App\Models\Store;
use App\Policies\ProductPolicy;
use App\Repositories\Media\MediaAttachmentRepository;
use Illuminate\Http\JsonResponse;

class MediaAttachmentController extends Controller
{
    public function __construct(private MediaAttachmentRepository $repository) {}

    private function authorizeTarget(Store $store, string $entity, int $entityId, string $operation): void
    {
        // 404 first: never disclose cross-store entity existence to the caller.
        $this->repository->findTarget($store->id, $entity, $entityId);

        match ($entity) {
            'products', 'variants' => $this->authorize($operation, [ProductPolicy::class, $store]),
            'categories' => $this->authorize($operation, [Category::class, $store]),
            'stores' => $this->authorize($operation, $store),
            default => abort(404),
        };
    }

    public function index(Store $store, string $entity, int $entityId): JsonResponse
    {
        $this->authorizeTarget($store, $entity, $entityId, 'view');
        $target = $this->repository->findTarget($store->id, $entity, $entityId);
        $attachments = $this->repository->relation($target, $entity)->with('media')->orderBy('sort_order')->get();

        return $this->success(MediaAttachmentResource::collection($attachments));
    }

    public function store(UploadAttachmentRequest $request, Store $store, string $entity, int $entityId, UploadAttachmentAction $action): JsonResponse
    {
        $this->authorizeTarget($store, $entity, $entityId, 'update');
        $attachment = $action->execute(UploadAttachmentDTO::fromRequest($request, $store->id, $entity, $entityId));

        return $this->success(new MediaAttachmentResource($attachment), 'Media uploaded', 201);
    }

    public function setMain(Store $store, string $entity, int $entityId, int $attachmentId, SetMainAttachmentAction $action): JsonResponse
    {
        $this->authorizeTarget($store, $entity, $entityId, 'update');
        $attachment = $action->execute(new SetMainAttachmentDTO($store->id, $entity, $entityId, $attachmentId));

        return $this->success(new MediaAttachmentResource($attachment), 'Main image updated');
    }

    public function destroy(Store $store, string $entity, int $entityId, int $attachmentId, DeleteAttachmentAction $action): JsonResponse
    {
        $this->authorizeTarget($store, $entity, $entityId, 'update');
        $action->execute($store->id, $entity, $entityId, $attachmentId);

        return $this->success(null, 'Media detached');
    }
}
