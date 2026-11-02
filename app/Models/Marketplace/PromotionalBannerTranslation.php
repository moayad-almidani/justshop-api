<?php

declare(strict_types=1);

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionalBannerTranslation extends Model
{
    protected $fillable = [
        'promotional_banner_id',
        'locale',
        'title',
        'subtitle',
        'button_text',
    ];

    public function promotionalBanner(): BelongsTo
    {
        return $this->belongsTo(PromotionalBanner::class);
    }
}
