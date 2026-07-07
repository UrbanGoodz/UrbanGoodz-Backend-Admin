<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzImportBatch extends Model
{
    protected $table = 'urban_goodz_import_batches';

    protected $fillable = [
        'city',
        'state',
        'category',
        'module',
        'source_query',
        'source_platforms',
        'total_found',
        'total_imported',
        'total_needs_review',
        'status',
        'admin_id',
        'completed_at',
    ];

    protected $casts = [
        'source_platforms' => 'array',
        'total_found' => 'integer',
        'total_imported' => 'integer',
        'total_needs_review' => 'integer',
        'admin_id' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function sourcedBusinesses()
    {
        return $this->hasMany(UrbanGoodzSourcedBusiness::class, 'import_batch_id');
    }

    public function sourcedProducts()
    {
        return $this->hasMany(UrbanGoodzSourcedProduct::class, 'import_batch_id');
    }
}
