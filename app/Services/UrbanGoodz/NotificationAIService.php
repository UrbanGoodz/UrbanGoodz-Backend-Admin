<?php

namespace App\Services\UrbanGoodz;

class NotificationAIService
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    public function generateSmartNotification(array $context): array
    {
        return [
            'title' => $context['title'] ?? 'Notification',
            'body' => $context['body'] ?? '',
            'priority' => 'normal',
            'channels' => ['push'],
        ];
    }
}
