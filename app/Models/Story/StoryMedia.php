<?php

declare(strict_types=1);

namespace App\Models\Story;

use App\Models\Media\MediaAttachment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryMedia extends MediaAttachment
{
    protected $table = 'story_media';

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }
}
