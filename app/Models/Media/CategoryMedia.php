<?php

declare(strict_types=1);

namespace App\Models\Media;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryMedia extends MediaAttachment
{
    protected $table = 'category_media';

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
