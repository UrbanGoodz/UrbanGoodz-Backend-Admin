<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzWaitlist extends Model
{
    protected $table = 'urban_goodz_waitlist';

    protected $fillable = [
        'full_name', 'email', 'phone', 'city', 'interest', 'message',
        'source', 'page', 'consent', 'user_agent', 'ip_address',
        'status', 'admin_notes',
    ];

    protected $casts = [
        'consent' => 'boolean',
    ];

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
