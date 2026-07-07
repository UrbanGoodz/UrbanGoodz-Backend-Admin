<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzBusinessTypeDefaultCapability extends Model
{
    protected $table = 'urban_goodz_business_type_default_capabilities';

    protected $fillable = [
        'business_type_id',
        'capability_id',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzBusinessType::class, 'business_type_id');
    }

    public function capability(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzCapability::class, 'capability_id');
    }
}
