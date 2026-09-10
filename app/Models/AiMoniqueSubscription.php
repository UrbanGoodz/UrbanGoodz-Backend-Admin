<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMoniqueSubscription extends Model
{
    public const STATUS_TRIAL_ACTIVE = 'trial_active';
    public const STATUS_TRIAL_EXPIRED = 'trial_expired';
    public const STATUS_ACTIVE_PAID = 'active_paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_DISABLED = 'disabled';

    public const POLICY_AUTO_CHARGE = 'auto_charge';
    public const POLICY_EXPLICIT_OPT_IN = 'explicit_opt_in';
    public const POLICY_AUTO_DISABLE = 'auto_disable';

    protected $fillable = [
        'account_type',
        'vendor_id',
        'admin_id',
        'store_id',
        'status',
        'trial_start_at',
        'trial_ends_at',
        'auto_continue',
        'price_per_month',
        'post_trial_policy',
        'stripe_customer_id',
        'stripe_subscription_id',
        'cancelled_at',
        'reactivated_at',
        'metadata',
    ];

    protected $casts = [
        'trial_start_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'reactivated_at' => 'datetime',
        'auto_continue' => 'boolean',
        'price_per_month' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function isTrialActive(): bool
    {
        if ($this->status !== self::STATUS_TRIAL_ACTIVE) {
            return false;
        }

        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isTrialExpired(): bool
    {
        if ($this->status === self::STATUS_TRIAL_EXPIRED) {
            return true;
        }

        return $this->status === self::STATUS_TRIAL_ACTIVE && $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function isPaidActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function daysRemaining(): int
    {
        if (!$this->trial_ends_at || $this->trial_ends_at->isPast()) {
            return 0;
        }

        return (int) ceil(Carbon::now()->diffInSeconds($this->trial_ends_at, false) / 86400);
    }

    public function isEntitled(): bool
    {
        if ($this->isPaidActive()) {
            return true;
        }

        if ($this->isTrialActive()) {
            return true;
        }

        return false;
    }
}
