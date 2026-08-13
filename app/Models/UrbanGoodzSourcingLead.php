<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzSourcingLead extends Model
{
    protected $table = 'urban_goodz_sourcing_leads';
    
    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'social_url',
        'website_url',
        'category',
        'city',
        'zone',
        'notes',
        'outreach_status',
        'outreach_assigned_to',
        'outreach_notes',
        'converted_record_type',
        'converted_record_id',
        'source',
        'source_url',
    ];

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'outreach_assigned_to');
    }

    public function convertedRecord()
    {
        return $this->morphTo();
    }
}
