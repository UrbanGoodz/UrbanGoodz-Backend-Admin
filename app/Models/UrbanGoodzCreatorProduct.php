<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzCreatorProduct extends Model
{
    protected $table = 'urban_goodz_creator_products';

    protected $fillable = [
        'creator_application_id', 'name', 'description', 'price',
        'currency', 'status', 'is_active', 'media_urls',
    ];

    protected $casts = ['price' => 'decimal:2', 'is_active' => 'boolean', 'media_urls' => 'array'];

    public function application()
    {
        return $this->belongsTo(UrbanGoodzCreatorApplication::class, 'creator_application_id');
    }
}
