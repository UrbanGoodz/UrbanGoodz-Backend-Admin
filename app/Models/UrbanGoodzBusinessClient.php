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
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'credit_limit' => 'decimal:2',
        'settings' => 'array',
    ];

    const STATUSES = ['pending', 'approved', 'suspended', 'inactive'];

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
}
