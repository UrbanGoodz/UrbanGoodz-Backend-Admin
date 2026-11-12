<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzOrderAnywhereReimbursement extends Model
{
    protected $table = 'urban_goodz_order_anywhere_reimbursements';

    protected $fillable = [
        'order_anywhere_request_id', 'delivery_man_id',
        'claimed_total', 'authorized_total', 'cap_total', 'approved_total',
        'status', 'receipt_path', 'receipt_mime', 'driver_notes', 'review_notes',
        'reviewed_by', 'reviewed_at', 'driver_earning_id',
    ];

    protected $hidden = ['receipt_path'];

    protected $casts = [
        'claimed_total' => 'decimal:2',
        'authorized_total' => 'decimal:2',
        'cap_total' => 'decimal:2',
        'approved_total' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function orderAnywhereRequest()
    {
        return $this->belongsTo(OrderAnywhereRequest::class, 'order_anywhere_request_id');
    }
}
