<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBusinessClientUser extends Authenticatable
{
    use SoftDeletes, Notifiable;

    protected $fillable = [
        'business_client_id', 'first_name', 'last_name', 'email', 'phone',
        'password', 'role', 'permissions', 'is_active', 'status', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    const ROLES = [
        'owner_admin', 'dispatcher', 'billing_manager', 'operations_manager',
        'compliance_manager', 'location_manager', 'read_only_viewer',
    ];

    const STATUSES = ['active', 'inactive', 'suspended'];

    const PERMISSIONS = [
        'business_jobs_create', 'business_jobs_view', 'business_jobs_manage',
        'business_jobs_cancel', 'business_quotes_view', 'business_quotes_approve',
        'business_invoices_view', 'business_payments_manage', 'business_reports_view',
        'business_users_manage', 'business_locations_manage', 'business_documents_manage',
        'business_custody_logs_view', 'business_api_access_manage',
        'scan_packages', 'view_package_pool', 'assign_packages_to_routes',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }
}
