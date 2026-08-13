<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UrbanGoodzProviderService extends Model
{
    protected $fillable = ['provider_id','category','name','description','duration_minutes','price_minor','deposit_minor','currency','requires_quote','is_active'];
    protected $casts = ['duration_minutes'=>'integer','price_minor'=>'integer','deposit_minor'=>'integer','requires_quote'=>'boolean','is_active'=>'boolean'];
}
