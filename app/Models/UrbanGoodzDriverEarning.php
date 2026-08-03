<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzDriverEarning extends Model
{
    use SoftDeletes;

    const EARNING_TYPES = [
        'per_package', 'pickup_bonus', 'completion_bonus',
        'priority_bonus', 'partial_pay', 'return_pay',
        'business_courier_delivery',
        'marketplace_delivery',
        'courier_parcel',
        'business_routes',
        'dedicated_routes',
        'logistics_loads',
        'medical_courier',
        'order_anywhere',
        'returns_exceptions',
        'load_board_delivery',
    ];

    const STATUSES = ['pending', 'approved', 'paid', 'held', 'disputed'];

    protected $fillable = [
        'delivery_man_id', 'business_client_job_id', 'package_id', 'dedicated_route_id', 'order_id',
        'load_id',
        'earning_type', 'amount', 'currency', 'status',
        'description', 'approved_by', 'approved_at', 'paid_at',
        'pricing_policy_id', 'pricing_policy_version', 'payout_model',
        'gross_cents', 'admin_fee_cents', 'net_cents',
        'calculation_inputs', 'policy_snapshot',
        'settlement_snapshot_id', 'idempotency_key',
        'financial_settlement_snapshot_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'calculation_inputs' => 'array',
        'policy_snapshot' => 'array',
        'gross_cents' => 'integer',
        'admin_fee_cents' => 'integer',
        'net_cents' => 'integer',
    ];

    public function driver()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }

    public function businessJob()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientJob::class, 'business_client_job_id');
    }

    public function package()
    {
        return $this->belongsTo(UrbanGoodzRoutePackage::class, 'package_id');
    }

    public function route()
    {
        return $this->belongsTo(UrbanGoodzDedicatedRoute::class, 'dedicated_route_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function loadBoardLoad()
    {
        return $this->belongsTo(UrbanGoodzLoadBoardLoad::class, 'load_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function financialSettlement()
    {
        return $this->belongsTo(
            UrbanGoodzFinancialSettlementSnapshot::class,
            'financial_settlement_snapshot_id'
        );
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeForDriver($query, $driverId)
    {
        return $query->where('delivery_man_id', $driverId);
    }
}
