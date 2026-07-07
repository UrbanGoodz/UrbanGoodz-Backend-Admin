<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEarnMoneyApplication extends Model
{
    protected $table = 'urban_goodz_earn_money_applications';

    protected $fillable = ['opportunity_id', 'applicant_name', 'applicant_email', 'status', 'admin_notes'];
}
