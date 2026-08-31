<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzSourcedImage extends Model
{
    protected $table = 'urban_goodz_sourced_images';

    protected $fillable = [
        'import_batch_id',
        'entity_type',
        'entity_id',
        'image_role',
        'image_url',
        'local_path',
        'source_url',
        'source_platform',
        'alt_text',
        'caption',
        'rights_status',
        'review_status',
        'api_visible',
        'shopper_visible',
        'width',
        'height',
        'format',
        'file_size_bytes',
        'content_hash',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'import_batch_id' => 'integer',
        'api_visible' => 'boolean',
        'shopper_visible' => 'boolean',
        'width' => 'integer',
        'height' => 'integer',
        'file_size_bytes' => 'integer',
    ];

    public function entity()
    {
        return $this->morphTo();
    }
}
