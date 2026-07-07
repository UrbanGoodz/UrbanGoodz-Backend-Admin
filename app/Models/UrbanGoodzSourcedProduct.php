<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class UrbanGoodzSourcedProduct extends Model
{
    protected $table = 'urban_goodz_sourced_products';

    protected $fillable = [
        'sourced_business_id',
        'store_id',
        'module_id',
        'category_id',
        'subcategory_id',
        'name',
        'slug',
        'short_description',
        'full_description',
        'price',
        'price_type',
        'currency',
        'stock_status',
        'item_type',
        'images',
        'thumbnail',
        'source_url',
        'source_type',
        'source_confidence',
        'fulfillment_type',
        'requires_quote',
        'requires_admin_review',
        'is_active',
        'is_public',
        'import_batch_id',
    ];

    protected $casts = [
        'sourced_business_id' => 'integer',
        'store_id' => 'integer',
        'module_id' => 'integer',
        'category_id' => 'integer',
        'subcategory_id' => 'integer',
        'price' => 'decimal:2',
        'images' => 'array',
        'source_confidence' => 'integer',
        'requires_quote' => 'boolean',
        'requires_admin_review' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'import_batch_id' => 'integer',
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

    public function sourcedBusiness()
    {
        return $this->belongsTo(UrbanGoodzSourcedBusiness::class, 'sourced_business_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function module()
    {
        return $this->belongsTo(Module::class, 'module_id');
    }
}
