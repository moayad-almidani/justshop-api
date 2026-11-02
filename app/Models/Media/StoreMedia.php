<?php

declare(strict_types=1);

namespace App\Models\Media;

use App\Models\Store;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreMedia extends MediaAttachment
{
    protected $table = 'store_media';

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
}
