<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UrbanGoodzSourcedBusiness extends Model
{
    protected $table = 'urban_goodz_sourced_businesses';

    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'display_name',
        'description',
        'short_description',
        'business_type',
        'module_id',
        'module_name',
        'category_ids',
        'tags',
        'phone',
        'email',
        'website',
        'social_links',
        'address',
        'city',
        'state',
        'country_code',
        'zip',
        'latitude',
        'longitude',
        'zone_id',
        'zone_name',
        'is_launch_market',
        'is_nationwide',
        'is_worldwide',
        'is_black_owned',
        'is_woman_owned',
        'is_local_business',
        'fulfillment_modes',
        'onboarding_status',
        'source_status',
        'source_urls',
        'data_confidence_score',
        'demand_score',
        'last_verified_at',
        'admin_review_status',
        'created_by_source',
    ];

    protected $casts = [
        'category_ids' => 'array',
        'tags' => 'array',
        'social_links' => 'array',
        'fulfillment_modes' => 'array',
        'source_urls' => 'array',
        'is_launch_market' => 'boolean',
        'is_nationwide' => 'boolean',
        'is_worldwide' => 'boolean',
        'is_black_owned' => 'boolean',
        'is_woman_owned' => 'boolean',
        'is_local_business' => 'boolean',
        'data_confidence_score' => 'integer',
        'demand_score' => 'integer',
        'last_verified_at' => 'datetime',
        'zone_id' => 'integer',
        'module_id' => 'integer',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name) . '-' . Str::random(5);
            }
        });
    }

    public function products()
    {
        return $this->hasMany(UrbanGoodzSourcedProduct::class, 'sourced_business_id');
    }

    public function images()
    {
        return $this->morphMany(UrbanGoodzSourcedImage::class, 'entity');
    }

    public function demandSignals()
    {
        return $this->hasMany(UrbanGoodzDemandSignal::class, 'matched_entity_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }
}
