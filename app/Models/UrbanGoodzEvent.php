<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzEvent extends Model
{
    protected $table = 'urban_goodz_events';

    protected $fillable = [
        'title', 'description', 'location', 'starts_at', 'ends_at',
        'organizer_name', 'organizer_contact', 'ticket_price', 'capacity',
        'status', 'image_url',
        'slug', 'category', 'venue_name', 'venue_address', 'latitude', 'longitude',
        'city', 'zone_id', 'recurrence_rule', 'recurrence_end', 'is_free', 'ticket_url',
        'age_restriction', 'images', 'creator_appearances', 'participating_businesses',
        'organiser_user_id', 'organiser_type', 'source', 'source_url', 'source_date',
        'validation_state', 'duplicate_state', 'approval_state', 'visibility_state',
        'failure_reason', 'retry_count', 'last_verified_at', 'published_at',
        'cancelled_at', 'expires_at', 'featured_at', 'admin_notes', 'moderation_notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime', 'ends_at' => 'datetime',
        'ticket_price' => 'decimal:2', 'capacity' => 'integer',
        'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
        'is_free' => 'boolean',
        'images' => 'array',
        'creator_appearances' => 'array',
        'participating_businesses' => 'array',
        'source_date' => 'datetime',
        'last_verified_at' => 'datetime',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'featured_at' => 'datetime',
        'recurrence_end' => 'datetime',
    ];

    public function venue()
    {
        return $this->belongsTo(UrbanGoodzEventVenue::class, 'venue_name', 'name');
    }

    public function organiser()
    {
        return $this->belongsTo(User::class, 'organiser_user_id');
    }

    public function saves()
    {
        return $this->hasMany(UrbanGoodzEventSave::class, 'event_id');
    }

    public function reminders()
    {
        return $this->hasMany(UrbanGoodzEventReminder::class, 'event_id');
    }

    public function sourcingRecord()
    {
        return $this->morphOne(UrbanGoodzSourcingRecord::class, 'sourceable');
    }

    public function creatorAppearanceProfiles()
    {
        return $this->belongsToMany(UrbanGoodzCreatorProfile::class, 'urban_goodz_event_creator', 'event_id', 'creator_profile_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeNearby($query, $lat, $lng, $radiusKm = 10)
    {
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
        return $query->selectRaw("{$haversine} AS distance", [$lat, $lng, $lat])
                     ->whereRaw("{$haversine} < ?", [$lat, $lng, $lat, $radiusKm]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('starts_at', now()->toDateString());
    }

    public function scopeThisWeekend($query)
    {
        $friday = now()->next('Friday')->startOfDay();
        $sunday = now()->next('Sunday')->endOfDay();
        return $query->whereBetween('starts_at', [$friday, $sunday]);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('starts_at', '>=', now());
    }

    public function scopeFree($query)
    {
        return $query->where('is_free', true);
    }

    public function scopePaid($query)
    {
        return $query->where('is_free', false);
    }

    public function scopeCategory($query, $cat)
    {
        return $query->where('category', $cat);
    }

    public function scopeCity($query, $city)
    {
        return $query->where('city', $city);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    public function scopeNotCancelled($query)
    {
        return $query->whereNull('cancelled_at');
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility_state', 'visible');
    }

    public function getIsExpiredAttribute()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
