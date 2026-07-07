<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzFile extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_files';

    protected $fillable = [
        'owner_id',
        'owner_type',
        'file_category',
        'original_name',
        'stored_path',
        'disk',
        'mime_type',
        'file_size',
        'metadata',
        'visibility',
        'uploaded_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'owner_id' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function owner()
    {
        return $this->morphTo();
    }
}
