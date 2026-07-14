<?php

namespace App\Services;

use App\CentralLogics\Helpers;

class FirebaseNotificationTransport
{
    public function send(string $token, array $payload): bool
    {
        return Helpers::send_push_notif_to_device($token, array_merge([
            'title' => 'Urban Goodz',
            'description' => '',
            'image' => '',
            'type' => 'general',
        ], $payload));
    }
}
