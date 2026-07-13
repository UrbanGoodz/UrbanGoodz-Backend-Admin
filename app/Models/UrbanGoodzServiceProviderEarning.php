<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanGoodzServiceProviderEarning extends Model { protected $fillable = ['provider_id','service_request_id','gross_amount_minor','platform_fee_minor','provider_amount_minor','currency','status']; }
