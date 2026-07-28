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

    const DISPATCH_ROLES = [
        'dispatch_owner'    => 'Dispatch Company Owner',
        'dispatch_manager'  => 'Dispatch Manager',
        'dispatcher'        => 'Dispatcher',
        'dispatch_readonly' => 'Read-Only Dispatcher',
        'dispatch_finance'  => 'Dispatch Finance',
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

    const DISPATCH_PERMISSIONS = [
        'dispatch_sourcing_view',
        'dispatch_loads_view',           'dispatch_loads_assign',        'dispatch_loads_manage',
        'dispatch_loads_create',         'dispatch_drivers_view',        'dispatch_drivers_assign',
        'dispatch_status_update',        'dispatch_commissions_view',    'dispatch_commissions_approve',
        'dispatch_territory_manage',     'dispatch_users_manage',        'dispatch_reports_view',
        'dispatch_notes_manage',         'dispatch_loads_cancel',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function assignedDispatchLoads()
    {
        return $this->hasMany(UrbanGoodzLoadBoardLoad::class, 'dispatcher_id');
    }

    public function dispatchCommissions()
    {
        return $this->hasMany(UrbanGoodzDispatchCommission::class, 'dispatcher_id');
    }

    public function getNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function isDispatchRole(): bool
    {
        return in_array($this->role, array_keys(self::DISPATCH_ROLES));
    }

    public function isDispatchOwner(): bool
    {
        return $this->role === 'dispatch_owner';
    }

    public function isDispatchManager(): bool
    {
        return $this->role === 'dispatch_manager';
    }

    public function isDispatcher(): bool
    {
        return $this->role === 'dispatcher';
    }

    public function isDispatchReadonly(): bool
    {
        return $this->role === 'dispatch_readonly';
    }

    public function isDispatchFinance(): bool
    {
        return $this->role === 'dispatch_finance';
    }

    public function canDispatch(): bool
    {
        return $this->client && $this->client->isDispatchCompany() && $this->isDispatchRole();
    }

    public function hasDispatchPermission(string $permission): bool
    {
        if ($this->role === 'dispatch_owner') {
            return true;
        }
        $userPerms = $this->permissions ?? [];
        return in_array($permission, $userPerms);
    }

    public function hasAnyDispatchPermission(array $permissions): bool
    {
        foreach ($permissions as $perm) {
            if ($this->hasDispatchPermission($perm)) {
                return true;
            }
        }
        return false;
    }
}
