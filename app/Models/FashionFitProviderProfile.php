<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FashionFitProviderProfile extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'service_categories' => 'array',
        'credentials' => 'array',
        'approved_at' => 'datetime',
    ];

    public function vendor() { return $this->belongsTo(Vendor::class); }
}
