<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzServiceProviderEarning extends Model
{
    protected $fillable = [
        'provider_id', 'service_request_id', 'gross_amount_minor', 'platform_fee_minor',
        'provider_amount_minor', 'currency', 'status', 'commission_percent',
        'settlement_batch', 'approved_at', 'settled_at', 'adjustment_minor', 'adjustment_reason',
    ];

    protected $casts = [
        'gross_amount_minor' => 'integer',
        'platform_fee_minor' => 'integer',
        'provider_amount_minor' => 'integer',
        'adjustment_minor' => 'integer',
        'approved_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceProvider::class, 'provider_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzServiceRequest::class, 'service_request_id');
    }

    /** Amount actually owed to the provider once admin adjustments are applied. */
    public function payableAmountMinor(): int
    {
        return max(0, (int) $this->provider_amount_minor + (int) $this->adjustment_minor);
    }
}
