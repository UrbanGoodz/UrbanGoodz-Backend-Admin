<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzSourcedImage extends Model
{
    protected $table = 'urban_goodz_sourced_images';

    protected $fillable = [
        'entity_type',
        'entity_id',
        'image_url',
        'local_path',
        'source_url',
        'source_platform',
        'alt_text',
        'caption',
        'rights_status',
        'review_status',
    ];

    protected $casts = [
        'entity_id' => 'integer',
    ];

    public function entity()
    {
        return $this->morphTo();
    }
}
