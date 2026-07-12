<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UrbanGoodzDispatchCommission extends Model
{
    use SoftDeletes;

    protected $table = 'urban_goodz_dispatch_commissions';

    protected $fillable = [
        'dispatch_company_id', 'dispatcher_id', 'load_id',
        'load_payout', 'commission_rate', 'commission_amount',
        'status', 'approved_at', 'approved_by', 'paid_at', 'notes',
    ];

    protected $casts = [
        'load_payout' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    const STATUSES = ['pending', 'approved', 'paid', 'disputed'];

    public function dispatchCompany()
    {
        return $this->belongsTo(UrbanGoodzBusinessClient::class, 'dispatch_company_id');
    }

    public function dispatcher()
    {
        return $this->belongsTo(UrbanGoodzBusinessClientUser::class, 'dispatcher_id');
    }

    public function load()
    {
        return $this->belongsTo(UrbanGoodzLoadBoardLoad::class, 'load_id');
    }

    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('dispatch_company_id', $companyId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
