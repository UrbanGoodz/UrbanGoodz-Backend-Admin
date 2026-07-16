<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class UrbanGoodzImpersonationSession extends Model
{
    protected $table = 'urban_goodz_impersonation_sessions';

    protected $fillable = [
        'admin_id', 'business_client_id', 'mode', 'session_token',
        'started_at', 'ended_at', 'exit_admin_id', 'ip_address',
        'user_agent', 'is_active',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->whereNull('ended_at');
    }

    public static function findByToken($token)
    {
        return static::active()->where('session_token', $token)->first();
    }

    public function end($adminId)
    {
        $this->ended_at = Carbon::now();
        $this->exit_admin_id = $adminId;
        $this->is_active = false;

        return $this->save();
    }
}
