<?php

declare(strict_types=1);

namespace App\Models\Media;

use App\Models\Product;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMedia extends MediaAttachment
{
    protected $table = 'product_media';

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
