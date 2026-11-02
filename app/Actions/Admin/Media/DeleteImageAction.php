<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\DTOs\Admin\Media\DeleteImageDTO;
use App\Exceptions\Media\InvalidMediaPathException;
use Illuminate\Support\Facades\Storage;

class DeleteImageAction
{
    /**
     * Execute image deletion.
     *
     * @throws InvalidMediaPathException
     */
    public function execute(DeleteImageDTO $dto): void
    {
        // Security: validate path belongs to the specified context
        if (!$dto->context->validatePath($dto->path)) {
            throw new InvalidMediaPathException(
                __('media.path_context_mismatch', [
                    'context' => $dto->context->value,
                ])
            );
        }

        // Central media is owned/linked by a different domain. Never allow
        // this legacy path-based endpoint to delete live centrally managed bytes.
        // Only media:prune may remove files inside stores/{id}/media/.
        if (preg_match('#^stores/[0-9]+/media/#', ltrim($dto->path, '/')) === 1) {
            throw new InvalidMediaPathException('Managed media must be detached through the media attachment API.');
        }

        // Check if file exists and delete
        if (Storage::exists($dto->path)) {
            Storage::delete($dto->path);
        }
    }
}
