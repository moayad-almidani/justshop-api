<?php

declare(strict_types=1);

namespace App\Models\Media;

use App\Enums\Media\MediaType;
use App\Models\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    protected $fillable = [
        'owner_store_id', 'type', 'disk', 'path', 'mime_type', 'size_bytes',
        'width', 'height', 'duration_sec', 'checksum',
    ];

    protected $casts = [
        'type' => MediaType::class,
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'duration_sec' => 'integer',
    ];

    public function ownerStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'owner_store_id');
    }

    public function conversions(): HasMany
    {
        return $this->hasMany(MediaConversion::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
