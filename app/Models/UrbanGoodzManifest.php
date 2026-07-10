<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzManifest extends Model
{
    use SoftDeletes;

    const STATUSES = ['draft', 'importing', 'import_complete', 'validating', 'validated', 'grouping', 'grouped', 'approved', 'canceled'];

    const SERVICE_TYPES = ['standard', 'express', 'same_day', 'next_day', 'medical', 'bulk', 'scheduled'];

    protected $fillable = [
        'business_client_id', 'manifest_name', 'manifest_session_id',
        'pickup_location_id', 'pickup_location_text',
        'service_date', 'service_type', 'status',
        'total_packages', 'scanned_packages', 'valid_packages', 'invalid_packages',
        'generated_routes_count',
        'created_by', 'approved_by', 'approved_at', 'notes',
    ];

    protected $casts = [
        'service_date' => 'date',
        'approved_at' => 'datetime',
        'total_packages' => 'integer',
        'scanned_packages' => 'integer',
        'valid_packages' => 'integer',
        'invalid_packages' => 'integer',
        'generated_routes_count' => 'integer',
    ];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function pickupLocation()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientLocation::class, 'pickup_location_id');
    }

    public function creator()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function packages()
    {
        return $this->hasMany(UrbanGoodzRoutePackage::class, 'manifest_id');
    }

    public function scopeReadyForOptimization($query)
    {
        return $query->whereIn('status', ['import_complete', 'validated', 'grouped'])
            ->where('total_packages', '>', 0);
    }

    public function isReadyForOptimization(): bool
    {
        return in_array($this->status, ['import_complete', 'validated', 'grouped'])
            && $this->total_packages > 0;
    }
}
