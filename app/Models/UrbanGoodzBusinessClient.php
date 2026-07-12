<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBusinessClient extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_name', 'legal_name', 'contact_name', 'email', 'contact_email',
        'phone', 'contact_phone', 'billing_email', 'billing_phone', 'website',
        'tax_id', 'business_type', 'address', 'city', 'state', 'postal_code',
        'country', 'status', 'notes', 'billing_terms', 'credit_limit',
        'payment_method_status', 'approved_by', 'approved_at', 'settings',
        'account_type', 'territory_states', 'territory_corridors',
        'dispatch_default_commission_rate',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'credit_limit' => 'decimal:2',
        'settings' => 'array',
        'territory_states' => 'array',
        'territory_corridors' => 'array',
        'dispatch_default_commission_rate' => 'decimal:2',
    ];

    const STATUSES = ['pending', 'approved', 'suspended', 'inactive'];

    const ACCOUNT_TYPES = ['business', 'dispatch_company'];

    const BILLING_TERMS = ['prepaid', 'due_on_receipt', 'net_7', 'net_15', 'net_30', 'custom'];

    const PAYMENT_METHOD_STATUSES = ['not_added', 'pending', 'verified', 'failed', 'disabled'];

    public function users()
    {
        return $this->hasMany(UrbanGoodzBusinessClientUser::class, 'business_client_id');
    }

    public function locations()
    {
        return $this->hasMany(UrbanGoodzBusinessClientLocation::class, 'business_client_id');
    }

    public function documents()
    {
        return $this->hasMany(UrbanGoodzBusinessClientDocument::class, 'business_client_id');
    }

    public function jobs()
    {
        return $this->hasMany(UrbanGoodzBusinessClientJob::class, 'business_client_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function dispatchLoads()
    {
        return $this->hasMany(UrbanGoodzLoadBoardLoad::class, 'dispatch_company_id');
    }

    public function dispatchCommissions()
    {
        return $this->hasMany(UrbanGoodzDispatchCommission::class, 'dispatch_company_id');
    }

    public function isDispatchCompany(): bool
    {
        return $this->account_type === 'dispatch_company';
    }

    public function isBusinessClient(): bool
    {
        return $this->account_type === 'business';
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeDispatchCompanies($query)
    {
        return $query->where('account_type', 'dispatch_company');
    }

    public function getTerritoryStatesAttribute(): ?array
    {
        return $this->attributes['territory_states'] ?? ($this->casts['territory_states'] ? json_decode($this->getRawOriginal('territory_states'), true) : null);
    }
}
