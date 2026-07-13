<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreatorReelTag extends Model
{
    protected $fillable = ['reel_id', 'store_id', 'taggable_type', 'taggable_id', 'label'];
}
