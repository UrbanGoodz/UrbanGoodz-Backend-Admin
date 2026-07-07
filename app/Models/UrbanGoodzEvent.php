<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEvent extends Model
{
    protected $table = 'urban_goodz_events';

    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at',
        'organizer_name', 'organizer_contact', 'ticket_price', 'capacity',
        'status', 'image_url',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime',
        'ticket_price' => 'decimal:2', 'capacity' => 'integer',
    ];
}
