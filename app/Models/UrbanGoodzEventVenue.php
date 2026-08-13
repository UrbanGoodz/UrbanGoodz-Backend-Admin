<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEventVenue extends Model
{
    protected $table = 'urban_goodz_event_venues';
    
    protected $fillable = [
        'name',
        'address',
        'latitude',
        'longitude',
        'city',
        'zone_id',
        'capacity',
        'description',
        'images',
        'contact_name',
        'contact_phone',
        'contact_email',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    public function events()
    {
        return $this->hasMany(UrbanGoodzEvent::class, 'venue_name', 'name');
    }
}
