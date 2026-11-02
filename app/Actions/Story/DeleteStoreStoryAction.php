<?php

declare(strict_types=1);

namespace App\Actions\Story;

use App\DTOs\Story\DeleteStoreStoryDTO;
use App\Models\Media\Media;
use App\Models\Story\Story;
use Illuminate\Support\Facades\DB;

final class DeleteStoreStoryAction
{
    public function execute(DeleteStoreStoryDTO $dto): void
    {
        DB::transaction(function () use ($dto): void {
            $story = Story::query()
                ->where('store_id', $dto->storeId)
                ->lockForUpdate()
                ->findOrFail($dto->storyId);

            $attachment = $story->mediaAttachment()->with('media')->first();
            $media = $attachment?->media;

            // story_media has no SoftDeletes; removing the attachment makes the
            // media eligible for the delayed orphan cleanup job.
            $attachment?->delete();

            if ($media instanceof Media) {
                $media->delete();
            }

            $story->delete();
        });
    }
}
