<?php

namespace App\Observers;

use App\Events\UrbanGoodzRealtimeUpdate;
use App\Models\Message;

class MessageObserver
{
    public function created(Message $message): void
    {
        if ((int) $message->conversation_id <= 0) {
            return;
        }

        // Only identifiers are broadcast. Message bodies and attachments remain
        // behind the authenticated conversation API.
        event(
            UrbanGoodzRealtimeUpdate::supportMessage(
                (int) $message->conversation_id,
                (int) $message->id
            )
        );
    }
}
