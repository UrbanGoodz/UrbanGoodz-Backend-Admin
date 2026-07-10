<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBusinessClientLocation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_client_id', 'name', 'type', 'address', 'city', 'state',
        'postal_code', 'country', 'latitude', 'longitude', 'contact_name',
        'contact_phone', 'contact_email', 'operating_hours', 'pickup_instructions',
        'delivery_instructions', 'notes', 'is_active',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    const TYPES = [
        'headquarters', 'pickup', 'dropoff', 'warehouse', 'clinic',
        'lab', 'pharmacy', 'office', 'event_location', 'other',
    ];

    const STATUSES = ['active', 'inactive'];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }
}
