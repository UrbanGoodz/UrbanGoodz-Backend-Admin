<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBookAnythingRequest extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_book_anything_requests';

    protected $fillable = [
        'request_number', 'customer_id', 'service_name', 'description',
        'preferred_date', 'preferred_time', 'location', 'budget_amount',
        'status', 'assigned_provider_id', 'admin_notes', 'completed_at',
    ];

    protected $casts = [
        'preferred_date' => 'date',
        'budget_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];
}
