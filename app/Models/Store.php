<?php

namespace App\Models;

use App\Enums\Store\StoreStatusEnum;
use App\Enums\Store\ProvisioningStatusEnum;
use App\Exceptions\Store\StoreNotFoundException;
use App\Models\Asset\StoreAsset;
use App\Models\Navigation\NavigationMenu;
use App\Models\Theme\Theme;
use App\Support\Media\MediaUrl;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Media\StoreMedia;
use App\Models\Story\Story;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'domain',
        'currency',
        'address_settings',
        'timezone',
        'is_active',
        'status',
        'status_changed_at',
        'status_changed_by_actor_type',
        'status_changed_by_actor_id',
        'setup_completed_at',
        'provisioning_status',
        'provisioning_progress',
        'provisioning_current_step',
        'provisioning_message',
        'provisioning_retryable',
        'provisioning_started_at',
        'provisioning_last_heartbeat_at',
        'provisioning_completed_at',
        'provisioning_failed_at',
        'provisioning_attempts',
        'provisioning_last_error',
        'is_grandfathered',
        'grandfathered_until',
        'stripe_account_id',
        'stripe_account_type',
        'stripe_details_submitted',
        'stripe_charges_enabled',
        'stripe_payouts_enabled',
        'stripe_onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'          => 'boolean',
            'status'             => StoreStatusEnum::class,
            'status_changed_at'  => 'datetime',
            'setup_completed_at' => 'datetime',
            'provisioning_status' => ProvisioningStatusEnum::class,
            'provisioning_progress' => 'integer',
            'provisioning_retryable' => 'boolean',
            'provisioning_started_at' => 'datetime',
            'provisioning_last_heartbeat_at' => 'datetime',
            'provisioning_completed_at' => 'datetime',
            'provisioning_failed_at' => 'datetime',
            'provisioning_attempts' => 'integer',
            'is_grandfathered' => 'boolean',
            'grandfathered_until' => 'datetime',
            'address_settings' => 'array',
            'stripe_details_submitted' => 'boolean',
            'stripe_charges_enabled' => 'boolean',
            'stripe_payouts_enabled' => 'boolean',
            'stripe_onboarded_at' => 'datetime',
        ];
    }

    /** Centralized attachment table; legacy images()/assets() retained during migration. */
    public function mediaLinks(): HasMany
    {
        return $this->hasMany(StoreMedia::class)->orderBy('sort_order');
    }

    // ── Relationships ──────────────────────────────────────────

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user')
            ->withPivot([
                'role',
                'lifecycle_status',
                'lifecycle_changed_at',
                'lifecycle_changed_by_actor_type',
                'lifecycle_changed_by_actor_id'
            ])
            ->withTimestamps();
    }

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** Stories are owned by the Store; product tagging is optional metadata. */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function carts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function themes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Theme::class);
    }

    /**
     * Resolve child merchant theme bindings by slug only. The lookup stays
     * scoped to this store so duplicate theme slugs across stores remain valid.
     */
    public function resolveChildRouteBinding($childType, $value, $field)
    {
        if ($childType === 'theme') {
            /** @var HasMany $query */
            $query = $this->themes();

            $resolved = (clone $query)->where('slug', (string) $value)->first();

            if ($resolved instanceof Theme) {
                return $resolved;
            }

            return null;
        }

        return parent::resolveChildRouteBinding($childType, $value, $field);
    }

    public function activeTheme(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Theme::class, 'active_theme_id');
    }

    public function navigationMenus(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NavigationMenu::class);
    }

    public function assets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(StoreAsset::class);
    }

    public function addressSettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(StoreAddressSetting::class);
    }

    public function shippingMethods(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }

    public function shippingZones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ShippingZone::class);
    }

    // ── Helpers ────────────────────────────────────────────────

    /**
     * Returns true if the store is fully operational.
     * Checks both the lifecycle status and the is_active flag for
     * backwards compatibility during the transition period.
     */
    public function isOperational(): bool
    {
        return $this->is_active && $this->status === StoreStatusEnum::ACTIVE;
    }

    /**
     * Check if store is grandfathered and grace period is still active.
     */
    public function isGrandfatheredAndActive(): bool
    {
        return $this->is_grandfathered 
            && $this->grandfathered_until 
            && $this->grandfathered_until->isFuture();
    }

    /**
     * Check if store grandfathering has expired.
     */
    public function isGrandfatheringExpired(): bool
    {
        return $this->is_grandfathered
            && $this->grandfathered_until
            && $this->grandfathered_until->isPast();
    }

    /**
     * Resolve merchant-facing store route segments by slug only.
     * For platform admin routes, support both ID and slug.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        // If value is numeric, treat as ID (for platform admin routes)
        if (is_numeric($value)) {
            $resolved = $this->newQuery()->where('id', (int) $value)->first();
            if ($resolved instanceof self) {
                return $resolved;
            }
        }

        // Otherwise, treat as slug (for merchant-facing routes)
        $resolved = $this->newQuery()
            ->where('slug', (string) $value)
            ->first();

        if ($resolved instanceof self) {
            return $resolved;
        }

        throw new StoreNotFoundException();
    }

    public function resolveRouteBindingOrFail($value, $field = null): Model
    {
        $resolved = $this->resolveRouteBinding($value, $field);

        if ($resolved instanceof Model) {
            return $resolved;
        }

        throw new StoreNotFoundException();
    }

    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
            set: fn (?string $value): ?string => MediaUrl::normalizeStorablePath($value),
        );
    }

    protected function faviconUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => MediaUrl::resolve($value),
            set: fn (?string $value): ?string => MediaUrl::normalizeStorablePath($value),
        );
    }

    // ── Stripe Connect Helpers ────────────────────────────────

    /**
     * Check if the store has a Stripe Connect account linked.
     */
    public function hasStripeAccount(): bool
    {
        return !empty($this->stripe_account_id);
    }

    /**
     * Check if the store can receive payments via Stripe Connect.
     * Requires a linked account with charges enabled.
     * Payouts may still be pending review, but funds are safely held on the connected account.
     */
    public function canReceivePayments(): bool
    {
        return $this->hasStripeAccount()
            && $this->stripe_charges_enabled;
    }
}
