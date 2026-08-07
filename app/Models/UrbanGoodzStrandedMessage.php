<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message in a Stranded request thread.
 *
 * A message may carry a precise coordinate or a photo instead of text. That
 * is the point: the thread exists mostly so people who cannot find each other
 * can fix that quickly, and "I'm by the blue skip behind the petrol station"
 * works better as a pin and a picture than as prose.
 */
class UrbanGoodzStrandedMessage extends Model
{
    protected $table = 'urban_goodz_stranded_messages';

    public const ROLE_CUSTOMER = 'customer';
    public const ROLE_RESPONDER = 'responder';
    public const ROLE_SYSTEM = 'system';

    public const TYPE_TEXT = 'text';
    public const TYPE_LOCATION = 'location';
    public const TYPE_PHOTO = 'photo';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'request_id', 'sender_role', 'sender_id', 'type', 'body',
        'latitude', 'longitude', 'accuracy_meters', 'photo_path', 'read_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy_meters' => 'float',
        'read_at' => 'datetime',
    ];

    /**
     * The stored path is on the private disk and must never be serialised
     * into a response. Photos are fetched through an authorising endpoint.
     */
    protected $hidden = ['photo_path'];

    public function request(): BelongsTo
    {
        return $this->belongsTo(UrbanGoodzStrandedRequest::class, 'request_id');
    }
}
