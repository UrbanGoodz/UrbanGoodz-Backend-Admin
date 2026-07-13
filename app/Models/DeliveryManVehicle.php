<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryManVehicle extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_insured' => 'boolean',
        'is_registered' => 'boolean',
        'is_active' => 'boolean',
        'insurance_expiry' => 'date',
        'registration_expiry' => 'date',
        'certifications' => 'array',
        'year' => 'integer',
    ];

    public function deliveryMan()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }
}
