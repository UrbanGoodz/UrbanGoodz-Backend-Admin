<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FashionFitPhoto extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];
    protected $casts = ['quality' => 'array', 'retake_instructions' => 'array'];

    public function file() { return $this->belongsTo(UrbanGoodzFile::class, 'file_id'); }
}
