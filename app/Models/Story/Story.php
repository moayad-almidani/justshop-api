<?php

declare(strict_types=1);

namespace App\Models\Story;

use App\Enums\Story\StoryStatusEnum;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Story extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'store_id',
        'product_id',
        'status',
        'caption',
        'published_at',
        'expires_at',
    ];

    protected $casts = [
        'status' => StoryStatusEnum::class,
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function mediaAttachment(): HasOne
    {
        return $this->hasOne(StoryMedia::class)->with('media');
    }

    public function scopeCurrentlyPublished(Builder $query): Builder
    {
        return $query
            ->where('status', StoryStatusEnum::PUBLISHED->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now())
            ->whereHas('mediaAttachment.media');
    }
}
