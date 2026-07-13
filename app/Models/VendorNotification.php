<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorNotification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'read_at' => 'datetime',
        'data' => 'array',
    ];

    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class, 'vendor_id');
    }
}
