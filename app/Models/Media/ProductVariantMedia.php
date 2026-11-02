<?php

declare(strict_types=1);

namespace App\Models\Media;

use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantMedia extends MediaAttachment
{
    protected $table = 'product_variant_media';

    public function entity(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
