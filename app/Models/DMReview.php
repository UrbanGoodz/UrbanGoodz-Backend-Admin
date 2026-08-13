<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class DMReview extends Model
{
    // Eloquent's naming convention guesses 'd_m_reviews' for this class,
    // but delivery-man reviews live in the shared `reviews` table
    // (delivery_man_id/rating columns) alongside store/item reviews. Without
    // this override, every call to DeliveryMan::rating() (e.g. the driver
    // duty-status toggle endpoint) 500s with "Table ... 'd_m_reviews'
    // doesn't exist".
    protected $table = 'reviews';

    protected $casts = [
        'delivery_man_id' => 'integer',
        'order_id' => 'integer',
        'user_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public function customer()
    {
        return $this->belongsTo(User::class,'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class,'delivery_man_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status',1);
    }
}
