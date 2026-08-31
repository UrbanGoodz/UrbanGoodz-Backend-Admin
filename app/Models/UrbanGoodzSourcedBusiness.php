<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UrbanGoodzSourcedBusiness extends Model
{
    protected $table = 'urban_goodz_sourced_businesses';

    protected $fillable = [
        'import_batch_id',
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
        'record_classification',
        'duplicate_of_business_id',
        'validation_status',
        'validation_errors',
        'source_verified',
        'api_visible',
        'shopper_visible',
        'reviewed_by',
        'reviewed_at',
        'hours',
        'hours_source_url',
        'hours_verified_at',
        'completeness_score',
        'completeness_breakdown',
        'enrichment_status',
        'next_enrichment_at',
        'field_provenance',
        'google_place_id',
    ];

    protected $casts = [
        'import_batch_id' => 'integer',
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
        'duplicate_of_business_id' => 'integer',
        'validation_errors' => 'array',
        'source_verified' => 'boolean',
        'api_visible' => 'boolean',
        'shopper_visible' => 'boolean',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
        'hours' => 'array',
        'hours_verified_at' => 'datetime',
        'completeness_score' => 'integer',
        'completeness_breakdown' => 'array',
        'next_enrichment_at' => 'datetime',
        'field_provenance' => 'array',
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
        return $this->hasMany(UrbanGoodzSourcedImage::class, 'entity_id')
            ->where('entity_type', 'business');
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

    public function importBatch()
    {
        return $this->belongsTo(UrbanGoodzImportBatch::class, 'import_batch_id');
    }

    public function duplicateOf()
    {
        return $this->belongsTo(self::class, 'duplicate_of_business_id');
    }

    public function scopeApiVisible($query)
    {
        return $query->where('api_visible', true)
            ->where('admin_review_status', 'approved')
            ->where('validation_status', 'valid')
            ->where('record_classification', 'production');
    }

    public function scopeShopperVisible($query)
    {
        return $query->apiVisible()->where('shopper_visible', true);
    }
}
