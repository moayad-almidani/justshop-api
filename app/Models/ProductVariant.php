<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Media\ProductVariantMedia;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'price',
        'compare_at_price',
        'cost_price',
        'quantity',
        'low_stock_threshold',
        'track_inventory',
        'manufacture_date',
        'expiry_date',
        'batch_number',
        'weight',
        'weight_unit',
        'position',
        'is_active',
    ];

    protected $casts = [
        'price'            => 'float',
        'compare_at_price' => 'float',
        'cost_price'       => 'float',
        'weight'           => 'float',
        'quantity'         => 'integer',
        'low_stock_threshold' => 'integer',
        'track_inventory'  => 'boolean',
        'is_active'        => 'boolean',
        'position'         => 'integer',
    ];

    /** Centralized attachment table; legacy images()/assets() retained during migration. */
    public function mediaLinks(): HasMany
    {
        return $this->hasMany(ProductVariantMedia::class)->orderBy('sort_order');
    }

    // ── Relationships ──────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * New system: option values from product_option_values
     * via variant_option_values pivot.
     */
    public function optionValues(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductOptionValue::class,
            'variant_option_values',
            'variant_id',
            'option_value_id',
        )->withPivot('option_id');
    }


    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    // ── Accessors ──────────────────────────────────────────────

    public function getPrimaryImageAttribute(): ?Image
    {
        return $this->images()->where('is_primary', true)->first();
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        if ($this->relationLoaded('mediaLinks') && $this->mediaLinks->isNotEmpty()) {
            $selected = $this->mediaLinks->first(fn ($link) => $link->is_main && $link->media?->type === \App\Enums\Media\MediaType::Image)
                ?? $this->mediaLinks->first(fn ($link) => $link->media?->type === \App\Enums\Media\MediaType::Image);
            if ($selected) {
                return $selected->media?->url();
            }
        }

        if ($this->relationLoaded('images')) {
            $primary = $this->images->where('is_primary', true)->first()
                ?? $this->images->first();

            return $primary?->full_url;
        }

        return $this->primary_image?->full_url;
    }
}
