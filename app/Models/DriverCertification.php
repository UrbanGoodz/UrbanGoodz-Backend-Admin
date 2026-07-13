<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverCertification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'is_required' => 'boolean',
    ];

    public function deliveryMan()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }
}
