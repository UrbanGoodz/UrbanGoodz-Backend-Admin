<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanGoodzProviderAvailability extends Model { protected $table = 'urban_goodz_provider_availability'; protected $fillable = ['provider_id','day_of_week','starts_at','ends_at','timezone','is_active']; protected $casts = ['is_active'=>'boolean']; }
