<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBatchPackage extends Model
{
    use SoftDeletes;

    const VALIDATION_STATUSES = ['pending', 'valid', 'invalid', 'needs_review'];
    const DUPLICATE_STATUSES = ['none', 'possible_duplicate', 'confirmed_duplicate', 'active_in_other_batch'];
    const GEOCODING_STATUSES = ['pending', 'success', 'failed', 'no_address'];
    const ROUTE_ASSIGNMENT_STATUSES = ['unassigned', 'assigned', 'locked', 'late'];
    const SOURCE_TYPES = ['barcode_scan', 'qr_scan', 'manual_entry', 'csv_import', 'spreadsheet_import', 'api', 'edi_manifest', 'existing_pool'];

    protected $fillable = [
        'intake_batch_id', 'business_client_id',
        'tracking_id', 'external_package_id', 'order_reference_number',
        'barcode', 'source_type', 'source_file_ref', 'source_manifest_row',
        'pickup_lat', 'pickup_lng', 'pickup_address', 'pickup_city', 'pickup_state', 'pickup_zip',
        'dropoff_lat', 'dropoff_lng', 'dropoff_address', 'dropoff_city', 'dropoff_state', 'dropoff_zip',
        'recipient_name', 'recipient_phone', 'recipient_email',
        'priority', 'delivery_window_start', 'delivery_window_end',
        'weight_lbs', 'volume_cubic_ft', 'package_type',
        'age_restricted', 'requires_signature', 'requires_photo', 'requires_custody',
        'special_instructions',
        'scanned_by_user_id', 'created_by_user_id', 'modified_by_user_id',
        'device_session_id', 'version',
        'validation_status', 'validation_errors',
        'duplicate_status', 'duplicate_of_package_id',
        'geocoding_status', 'geocoding_result',
        'route_assignment_status', 'dedicated_route_id', 'stop_order',
        'is_active', 'finalized_at',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'weight_lbs' => 'decimal:2',
        'volume_cubic_ft' => 'decimal:2',
        'delivery_window_start' => 'datetime',
        'delivery_window_end' => 'datetime',
        'age_restricted' => 'boolean',
        'requires_signature' => 'boolean',
        'requires_photo' => 'boolean',
        'requires_custody' => 'boolean',
        'version' => 'integer',
        'validation_errors' => 'array',
        'geocoding_result' => 'array',
        'stop_order' => 'integer',
        'is_active' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    // --- Relationships ---

    public function batch()
    {
        return $this->belongsTo(UrbanGoodzIntakeBatch::class, 'intake_batch_id');
    }

    public function businessClient()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function modifiedBy()
    {
        return $this->belongsTo(User::class, 'modified_by_user_id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(UrbanGoodzBatchPackage::class, 'duplicate_of_package_id');
    }

    public function duplicates()
    {
        return $this->hasMany(UrbanGoodzBatchPackage::class, 'duplicate_of_package_id');
    }

    public function dedicatedRoute()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function audits()
    {
        return $this->hasMany(BatchPackageAudit::class, 'batch_package_id');
    }

    // --- Optimistic Concurrency ---

    public function updateWithOwnership(array $attributes, int $userId): bool
    {
        $currentVersion = $this->version;

        $attributes['modified_by_user_id'] = $userId;
        $attributes['version'] = $currentVersion + 1;

        $affected = static::where('id', $this->id)
            ->where('version', $currentVersion)
            ->update($attributes);

        if ($affected === 0) {
            return false;
        }

        $this->fill($attributes);
        $this->syncOriginal();

        return true;
    }

    public function getConflictInfo(): array
    {
        $current = static::find($this->id);
        return [
            'current_version' => $current->version,
            'your_version' => $this->version,
            'modified_by' => $current->modifiedBy?->name ?? 'unknown',
            'modified_at' => $current->updated_at?->toIso8601String(),
            'current_values' => $current->toArray(),
        ];
    }

    // --- Duplicate detection criteria ---

    public function getDuplicateCriteria(): array
    {
        $criteria = [];

        if ($this->barcode) {
            $criteria['barcode'] = strtolower(trim($this->barcode));
        }

        if ($this->tracking_id) {
            $criteria['tracking_id'] = strtolower(trim($this->tracking_id));
        }

        if ($this->business_client_id && $this->external_package_id) {
            $criteria['external_package'] = [
                'business_client_id' => $this->business_client_id,
                'external_package_id' => strtolower(trim($this->external_package_id))
            ];
        }

        if ($this->order_reference_number) {
            $criteria['order_ref'] = strtolower(trim($this->order_reference_number));
        }

        if ($this->source_manifest_row) {
            $criteria['manifest_row'] = [
                'business_client_id' => $this->business_client_id,
                'source_manifest_row' => strtolower(trim($this->source_manifest_row))
            ];
        }

        if ($this->dropoff_address && $this->dropoff_city && $this->recipient_name) {
            $criteria['recipient_address'] = [
                'dropoff_address' => strtolower(trim($this->dropoff_address)),
                'dropoff_city' => strtolower(trim($this->dropoff_city)),
                'dropoff_state' => strtolower(trim($this->dropoff_state ?? '')),
                'dropoff_zip' => strtolower(trim($this->dropoff_zip ?? '')),
                'recipient_name' => strtolower(trim($this->recipient_name))
            ];
        }

        return $criteria;
    }

    // --- Validation ---

    public function runValidation(): array
    {
        $errors = [];

        if (!$this->dropoff_address && !$this->dropoff_lat) {
            $errors[] = ['field' => 'dropoff_address', 'message' => 'Dropoff address or coordinates required'];
        }

        if ($this->dropoff_lat && ($this->dropoff_lat < -90 || $this->dropoff_lat > 90)) {
            $errors[] = ['field' => 'dropoff_lat', 'message' => 'Invalid latitude'];
        }

        if ($this->dropoff_lng && ($this->dropoff_lng < -180 || $this->dropoff_lng > 180)) {
            $errors[] = ['field' => 'dropoff_lng', 'message' => 'Invalid longitude'];
        }

        if ($this->weight_lbs !== null && $this->weight_lbs < 0) {
            $errors[] = ['field' => 'weight_lbs', 'message' => 'Weight cannot be negative'];
        }

        if ($this->delivery_window_start && $this->delivery_window_end) {
            if ($this->delivery_window_start > $this->delivery_window_end) {
                $errors[] = ['field' => 'delivery_window', 'message' => 'Window start must precede end'];
            }
        }

        $status = empty($errors) ? 'valid' : 'invalid';
        $this->update([
            'validation_status' => $status,
            'validation_errors' => empty($errors) ? null : $errors,
        ]);

        return $errors;
    }

    public function hasValidCoordinates(): bool
    {
        return $this->dropoff_lat != 0 && $this->dropoff_lng != 0
            && $this->dropoff_lat > -90 && $this->dropoff_lat < 90
            && $this->dropoff_lng > -180 && $this->dropoff_lng < 180;
    }

    public function addressKey(): string
    {
        return strtolower(trim(
            ($this->dropoff_address ?? '') . '|' .
            ($this->dropoff_city ?? '') . '|' .
            ($this->dropoff_state ?? '') . '|' .
            ($this->dropoff_zip ?? '')
        ));
    }
}
