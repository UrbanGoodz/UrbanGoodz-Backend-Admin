<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanGoodzServiceBookingEvent extends Model { protected $fillable = ['service_request_id','actor_type','actor_id','from_status','to_status','metadata']; protected $casts = ['metadata'=>'array']; }
