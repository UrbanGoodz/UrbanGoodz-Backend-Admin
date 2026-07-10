<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzBusinessClientDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_client_id', 'uploaded_by', 'document_type', 'document_name',
        'file_path', 'file_type', 'file_size', 'status', 'notes',
        'expires_at', 'verified_at', 'verified_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'expires_at' => 'date',
        'verified_at' => 'datetime',
    ];

    const TYPES = [
        'contract', 'insurance', 'tax_document', 'operating_agreement',
        'compliance', 'medical_courier_requirement', 'invoice_support', 'other',
    ];

    const STATUSES = ['pending', 'approved', 'rejected', 'active', 'archived'];

    public function client()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'business_client_id');
    }

    public function uploader()
    {
        return $this->belongsTo(Admin::class, 'uploaded_by');
    }

    public function verifier()
    {
        return $this->belongsTo(Admin::class, 'verified_by');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
