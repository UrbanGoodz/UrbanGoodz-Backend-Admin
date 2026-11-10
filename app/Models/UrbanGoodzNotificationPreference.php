<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UrbanGoodzNotificationPreference extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'push_enabled' => 'boolean', 'email_enabled' => 'boolean', 'sms_consent' => 'boolean', 'promotions_opt_in' => 'boolean',
        'sms_consent_at' => 'datetime', 'promotions_opt_in_at' => 'datetime',
    ];

    /** Events that are marketing, not service messages. */
    public const PROMOTIONAL_EVENTS = ['promotion_available'];

    public static function defaultsFor(string $type, int $id): self
    {
        return new self(['recipient_type' => $type, 'recipient_id' => $id, 'push_enabled' => true, 'email_enabled' => true, 'sms_consent' => false, 'promotions_opt_in' => false]);
    }

    public static function forRecipient(string $type, int $id): self
    {
        return self::where('recipient_type', $type)->where('recipient_id', $id)->first() ?? self::defaultsFor($type, $id);
    }

    /** May this message go out on this channel to this person? In-app copies are always kept. */
    public static function allows(string $type, int $id, string $channel, string $event): bool
    {
        $channel = strtolower($channel);
        if (in_array($channel, ['in_app', 'database', 'websocket', 'admin_alert'], true)) {
            return true;
        }

        $pref = self::forRecipient($type, $id);

        if (in_array($event, self::PROMOTIONAL_EVENTS, true) && ! $pref->promotions_opt_in) {
            return false;
        }

        return match ($channel) {
            'push', 'firebase_push' => $pref->push_enabled,
            'email' => $pref->email_enabled,
            'sms' => $pref->sms_consent,
            default => true,
        };
    }
}
