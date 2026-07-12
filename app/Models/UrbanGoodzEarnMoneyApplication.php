<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UrbanGoodzEarnMoneyApplication extends Model
{
    protected $table = 'urban_goodz_earn_money_applications';

    protected $fillable = ['opportunity_id', 'applicant_name', 'applicant_email', 'status', 'admin_notes'];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzEarnMoneyOpportunity::class, 'opportunity_id');
    }
}
