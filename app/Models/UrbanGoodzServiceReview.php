<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanGoodzServiceReview extends Model { protected $fillable = ['service_request_id','provider_id','user_id','rating','comment']; }
