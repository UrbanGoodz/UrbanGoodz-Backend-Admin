<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzDiscoverySearch extends Model
{
    protected $table = 'urban_goodz_discovery_searches';

    protected $fillable = ['query', 'customer_ip', 'source', 'result_count', 'was_fulfilled'];

    protected $casts = ['result_count' => 'integer', 'was_fulfilled' => 'boolean'];
}
