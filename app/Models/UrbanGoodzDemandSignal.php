<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzDemandSignal extends Model
{
    protected $table = 'urban_goodz_demand_signals';

    protected $fillable = [
        'customer_id',
        'query_text',
        'requested_item',
        'requested_vendor',
        'source',
        'matched_entity_id',
        'matched_product_id',
        'city',
        'state',
        'zone_id',
        'demand_count',
        'opportunity_score',
        'converted_to_business_id',
        'converted_to_product_id',
        'converted_to_order_anywhere_request_id',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'matched_entity_id' => 'integer',
        'matched_product_id' => 'integer',
        'zone_id' => 'integer',
        'demand_count' => 'integer',
        'opportunity_score' => 'integer',
        'converted_to_business_id' => 'integer',
        'converted_to_product_id' => 'integer',
        'converted_to_order_anywhere_request_id' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function matchedEntity()
    {
        return $this->belongsTo(UrbanGoodzSourcedBusiness::class, 'matched_entity_id');
    }

    public function matchedProduct()
    {
        return $this->belongsTo(UrbanGoodzSourcedProduct::class, 'matched_product_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
