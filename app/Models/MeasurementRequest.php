<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeasurementRequest extends Model
{
    public const PAYMENT_STATUSES = ['not_required', 'pending', 'paid', 'waived', 'failed', 'refunded'];
    public const MEASUREMENT_STATUSES = ['not_started', 'manual_only', 'photos_needed', 'photos_uploaded', 'estimating', 'estimated', 'needs_customer_review', 'ready_for_tailor_review', 'tailor_adjusted', 'approved', 'cancelled'];
    public const REVIEW_STATUSES = ['pending', 'needs_more_info', 'accepted', 'adjusted_by_tailor', 'ready_to_quote', 'completed_review'];
    public const FACE_BLUR_STATUSES = ['not_required', 'pending', 'blurred', 'cropped', 'unavailable', 'failed'];
    public const PRIVACY_REVIEW_STATUSES = ['pending', 'approved', 'needs_review', 'blocked'];

    protected $table = 'urban_goodz_measurement_requests';

    protected $fillable = [
        'customer_id',
        'vendor_id',
        'tailor_id',
        'measurement_profile_id',
        'preferred_fit',
        'height',
        'chest_bust',
        'waist',
        'hips',
        'inseam',
        'sleeve_length',
        'shoulder_width',
        'neck',
        'source',
        'front_photo_path',
        'side_photo_path',
        'back_photo_path',
        'front_photo_file_id',
        'side_photo_file_id',
        'back_photo_file_id',
        'face_blur_enabled',
        'face_blur_status',
        'privacy_review_status',
        'platform_measurement_fee',
        'vendor_review_fee',
        'total_measurement_fee',
        'currency',
        'payment_required',
        'payment_status',
        'free_tester_mode',
        'measurement_status',
        'review_status',
        'tailor_notes',
        'admin_notes',
        'quote_amount',
        'mockup_reference',
        'corrected_measurements',
        'item_wanted',
        'request_type',
        'budget',
        'due_date',
        'consent_to_share_photos',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'vendor_id' => 'integer',
        'tailor_id' => 'integer',
        'measurement_profile_id' => 'integer',
        'height' => 'decimal:2',
        'chest_bust' => 'decimal:2',
        'waist' => 'decimal:2',
        'hips' => 'decimal:2',
        'inseam' => 'decimal:2',
        'sleeve_length' => 'decimal:2',
        'shoulder_width' => 'decimal:2',
        'front_photo_file_id' => 'integer',
        'side_photo_file_id' => 'integer',
        'back_photo_file_id' => 'integer',
        'face_blur_enabled' => 'boolean',
        'platform_measurement_fee' => 'decimal:2',
        'vendor_review_fee' => 'decimal:2',
        'total_measurement_fee' => 'decimal:2',
        'payment_required' => 'boolean',
        'free_tester_mode' => 'boolean',
        'quote_amount' => 'decimal:2',
        'budget' => 'decimal:2',
        'consent_to_share_photos' => 'boolean',
    ];

    public static function testerDefaults(): array
    {
        return [
            'source' => 'manual',
            'face_blur_enabled' => true,
            'face_blur_status' => 'pending_review',
            'privacy_review_status' => 'pending',
            'platform_measurement_fee' => 0,
            'vendor_review_fee' => 0,
            'total_measurement_fee' => 0,
            'currency' => 'USD',
            'payment_required' => false,
            'payment_status' => 'waived',
            'free_tester_mode' => false,
            'measurement_status' => 'not_started',
            'review_status' => 'pending',
        ];
    }

    public function frontPhotoFile()
    {
        return $this->belongsTo(UrbanGoodzFile::class, 'front_photo_file_id');
    }

    public function sidePhotoFile()
    {
        return $this->belongsTo(UrbanGoodzFile::class, 'side_photo_file_id');
    }

    public function backPhotoFile()
    {
        return $this->belongsTo(UrbanGoodzFile::class, 'back_photo_file_id');
    }

    public function scopeForVendor($query, ?int $vendorId)
    {
        return $query->where('vendor_id', $vendorId ?? 0);
    }
}
