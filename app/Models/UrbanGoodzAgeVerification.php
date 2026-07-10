<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzAgeVerification extends Model
{
    const STATUSES = ['pending', 'verified', 'failed', 'refused'];

    const REFUSAL_REASONS = [
        'no_id_provided', 'id_expired', 'recipient_underage',
        'recipient_name_mismatch', 'recipient_visibly_intoxicated_or_unsafe',
        'recipient_unavailable', 'address_mismatch', 'driver_safety_issue',
        'other_admin_review',
    ];

    const ADMIN_REVIEW_STATUSES = ['pending', 'reviewed', 'resolved', 'escalated'];

    protected $fillable = [
        'package_id', 'route_id', 'order_id', 'driver_id',
        'verification_status', 'refusal_reason', 'driver_notes',
        'id_type_checked', 'recipient_name_verified', 'recipient_dob_verified',
        'recipient_age_confirmed',
        'verification_attempted_at',
        'signature_captured', 'proof_photo_captured',
        'admin_review_required', 'admin_review_status',
        'admin_reviewed_by', 'admin_reviewed_at', 'admin_notes',
    ];

    protected $casts = [
        'recipient_dob_verified' => 'date',
        'verification_attempted_at' => 'datetime',
        'admin_reviewed_at' => 'datetime',
        'signature_captured' => 'boolean',
        'proof_photo_captured' => 'boolean',
        'admin_review_required' => 'boolean',
        'recipient_age_confirmed' => 'boolean',
    ];

    public function package()
    {
        return $this->belongsTo(UrbanGoodzRoutePackage::class, 'package_id');
    }

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'route_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'driver_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(Admin::class, 'admin_reviewed_by');
    }

    public function scopeNeedsAdminReview($query)
    {
        return $query->where('admin_review_required', true)
            ->whereIn('admin_review_status', ['pending', 'escalated']);
    }
}
