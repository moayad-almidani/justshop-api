<?php

declare(strict_types=1);

namespace App\Models\Marketplace;

use App\Enums\Marketplace\PromotionalBannerStatusEnum;
use App\Enums\Media\MediaType;
use App\Models\Media\Media;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PromotionalBanner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'image_media_id',
        'status',
        'target_url',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'status' => PromotionalBannerStatusEnum::class,
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'image_media_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PromotionalBannerTranslation::class);
    }

    /** Match Product/Category translation lookup: requested locale, then first available translation. */
    public function translation(?string $locale = null): ?PromotionalBannerTranslation
    {
        $locale = $locale ?? app()->getLocale();

        return $this->translations->where('locale', $locale)->first()
            ?? $this->translations->first();
    }

    public function translated(string $key, ?string $locale = null): ?string
    {
        return $this->translation($locale)?->{$key};
    }

    public function scopeVisibleNow(Builder $query): Builder
    {
        return $query
            ->where('status', PromotionalBannerStatusEnum::PUBLISHED->value)
            ->where(fn (Builder $schedule): Builder => $schedule
                ->whereNull('starts_at')
                ->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $schedule): Builder => $schedule
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()))
            ->whereHas('media', fn (Builder $media): Builder => $media->where('type', MediaType::Image->value));
    }
}
