<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzFinancialRule extends Model
{
    public const FAMILIES = [
        'business_commission',
        'driver_compensation',
        'driver_premium',
        'driver_admin_fee',
    ];

    public const CALCULATION_TYPES = [
        'percentage',
        'fixed',
        'flat',
        'per_mile',
        'per_package',
        'per_stop',
        'per_route',
        'hourly',
        'per_return',
        'per_exception',
    ];

    public const SCOPES = [
        'platform',
        'service_type',
        'zone',
        'business',
        'provider',
        'driver',
    ];

    protected $fillable = [
        'rule_key',
        'version',
        'name',
        'rule_family',
        'calculation_type',
        'amount_cents',
        'rate_basis_points',
        'scope_type',
        'scope_key',
        'service_type',
        'priority',
        'visibility_roles',
        'effective_from',
        'effective_to',
        'is_active',
        'supersedes_id',
        'created_by_admin_id',
        'change_reason',
    ];

    protected $casts = [
        'version' => 'integer',
        'amount_cents' => 'integer',
        'rate_basis_points' => 'integer',
        'priority' => 'integer',
        'visibility_roles' => 'array',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function supersedes()
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
