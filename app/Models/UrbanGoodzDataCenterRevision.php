<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzDataCenterRevision extends Model
{
    protected $table = 'urban_goodz_data_center_revisions';

    protected $fillable = [
        'import_batch_id',
        'action',
        'snapshot',
        'admin_id',
        'reason',
    ];

    protected $casts = [
        'import_batch_id' => 'integer',
        'snapshot' => 'array',
        'admin_id' => 'integer',
    ];

    public function batch()
    {
        return $this->belongsTo(UrbanGoodzImportBatch::class, 'import_batch_id');
    }
}
