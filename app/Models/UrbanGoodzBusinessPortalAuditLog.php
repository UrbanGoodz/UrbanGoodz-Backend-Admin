<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzBusinessPortalAuditLog extends Model
{
    protected $table = 'urban_goodz_business_portal_audit_logs';

    protected $fillable = [
        'admin_id', 'business_client_user_id', 'business_client_id', 'action',
        'mode', 'target_type', 'target_id', 'details', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function user()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'business_client_user_id');
    }

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function scopeForClient($query, $clientId)
    {
        return $query->where('business_client_id', $clientId);
    }
}
