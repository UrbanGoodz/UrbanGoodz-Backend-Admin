<?php

namespace App\Services\UrbanGoodz;

use Illuminate\Support\Facades\Log;

class NotificationAIService
{
    public function __construct(
        private UrbanGoodzAIService $ai
    ) {}

    /**
     * AI-powered smart notification generation.
     * Personalizes, prioritizes, and optimizes notification content.
     */
    public function generateSmartNotification(array $context): array
    {
        $prompt = $this->buildNotificationPrompt($context);

        try {
            $response = $this->ai->chat($prompt, $this->encodeContext($context));
            $parsed = $this->decodeResponse($response);

            if ($parsed === null) {
                return $this->fallbackNotification($context);
            }

            return [
                'title' => $parsed['title'] ?? $context['title'] ?? 'Notification',
                'body' => $parsed['body'] ?? $context['body'] ?? '',
                'priority' => $parsed['priority'] ?? 'normal',
                'channels' => $parsed['channels'] ?? ['push'],
                'personalization' => $parsed['personalization'] ?? null,
                'sentiment' => $parsed['sentiment'] ?? 'neutral',
                'action_suggestion' => $parsed['action_suggestion'] ?? null,
                'source' => 'ai_personalized',
            ];
        } catch (\Exception $e) {
            Log::warning('NotificationAIService AI generation failed', ['error' => $e->getMessage()]);
            return $this->fallbackNotification($context);
        }
    }

    /**
     * AI-powered notification digest/summary.
     */
    public function generateDigest(array $notifications, array $recipientContext): array
    {
        $recipientRole = $recipientContext['role'] ?? 'user';
        $prompt = "Summarize these notifications into a concise digest for a {$recipientRole}. "
            . "Prioritize by urgency and relevance. Group related items.\n\n"
            . "Notifications: " . json_encode($notifications) . "\n"
            . "Recipient context: " . json_encode($recipientContext) . "\n\n"
            . "Return JSON: {\"digest_title\": \"...\", \"digest_body\": \"...\", \"highlights\": [...], \"action_items\": [...], \"priority_notifications\": [...], \"can_safely_batch\": true|false}";

        try {
            $response = $this->ai->chat($prompt, $this->encodeContext($recipientContext));
            $parsed = $this->decodeResponse($response);

            if ($parsed === null) {
                return $this->fallbackDigest($notifications);
            }

            return [
                'digest_title' => $parsed['digest_title'] ?? 'Notification Digest',
                'digest_body' => $parsed['digest_body'] ?? '',
                'highlights' => $parsed['highlights'] ?? [],
                'action_items' => $parsed['action_items'] ?? [],
                'priority_notifications' => $parsed['priority_notifications'] ?? [],
                'can_safely_batch' => $this->toBool($parsed['can_safely_batch'] ?? true),
                'source' => 'ai_digest',
            ];
        } catch (\Exception $e) {
            Log::warning('NotificationAIService digest generation failed', ['error' => $e->getMessage()]);
            return $this->fallbackDigest($notifications);
        }
    }

    /**
     * AI-powered notification prioritization.
     */
    public function prioritizeNotifications(array $notifications): array
    {
        $prompt = "Prioritize these notifications by urgency and user impact. "
            . "Consider: time sensitivity, financial impact, safety, operational importance.\n\n"
            . "Notifications: " . json_encode($notifications) . "\n\n"
            . "Return JSON: {\"prioritized\": [{\"id\": \"...\", \"priority\": \"urgent|high|normal|low\", \"reason\": \"...\"}]}";

        try {
            $response = $this->ai->chat($prompt, $this->encodeContext($notifications));
            $parsed = $this->decodeResponse($response);

            if ($parsed === null) {
                return $this->fallbackPrioritization();
            }

            return [
                'prioritized' => $parsed['prioritized'] ?? [],
                'source' => 'ai_prioritization',
            ];
        } catch (\Exception $e) {
            Log::warning('NotificationAIService prioritization failed', ['error' => $e->getMessage()]);
            return $this->fallbackPrioritization();
        }
    }

    private function buildNotificationPrompt(array $context): string
    {
        $role = $context['recipient_type'] ?? 'customer';
        $eventType = $context['event_type'] ?? 'general';

        return "Generate a personalized notification for a {$role}. "
            . "Event: {$eventType}.\n"
            . "Context: " . json_encode($context) . "\n\n"
            . "Requirements:\n"
            . "- Use plain, friendly language\n"
            . "- Be concise (under 150 words for body)\n"
            . "- Include relevant details (order numbers, amounts, times)\n"
            . "- Suggest next action if appropriate\n"
            . "- Match urgency to event type\n"
            . "- Never include sensitive data (passwords, tokens, full card numbers)\n\n"
            . "Return JSON: {\"title\": \"...\", \"body\": \"...\", \"priority\": \"low|normal|high|urgent\", \"channels\": [...], \"personalization\": \"...\", \"sentiment\": \"positive|neutral|negative\", \"action_suggestion\": \"...\"}";
    }

    private function fallbackNotification(array $context): array
    {
        $eventType = $context['event_type'] ?? 'general';
        $role = $context['recipient_type'] ?? 'customer';

        $templates = [
            'order_created' => ['title' => 'Order Received', 'body' => 'Your order has been received and is being reviewed.', 'priority' => 'normal'],
            'order_delivered' => ['title' => 'Order Delivered', 'body' => 'Your order has been delivered. Thank you!', 'priority' => 'normal'],
            'order_cancelled' => ['title' => 'Order Cancelled', 'body' => 'Your order has been cancelled.', 'priority' => 'high'],
            'fraud_alert' => ['title' => 'Security Alert', 'body' => 'Unusual activity detected on your account.', 'priority' => 'urgent'],
            'quote_received' => ['title' => 'Quote Ready', 'body' => 'You have a new quote ready for review.', 'priority' => 'normal'],
            'driver_assigned' => ['title' => 'Driver Assigned', 'body' => 'A driver has been assigned to your order.', 'priority' => 'normal'],
        ];

        $template = $templates[$eventType] ?? ['title' => ucfirst(str_replace('_', ' ', $eventType)), 'body' => $context['body'] ?? 'You have a new notification.', 'priority' => 'normal'];

        return [
            'title' => $template['title'],
            'body' => $template['body'],
            'priority' => $template['priority'],
            'channels' => ['push'],
            'personalization' => null,
            'sentiment' => 'neutral',
            'action_suggestion' => null,
            'source' => 'fallback',
        ];
    }

    private function fallbackDigest(array $notifications): array
    {
        return [
            'digest_title' => 'Notifications',
            'digest_body' => count($notifications) . ' new notifications',
            'highlights' => [],
            'action_items' => [],
            'priority_notifications' => [],
            'can_safely_batch' => true,
            'source' => 'fallback',
        ];
    }

    private function fallbackPrioritization(): array
    {
        return ['prioritized' => [], 'source' => 'fallback'];
    }

    private function encodeContext(array $context): string
    {
        $encoded = json_encode($context);
        return is_string($encoded) ? $encoded : '{}';
    }

    private function decodeResponse(mixed $response): ?array
    {
        if (is_array($response)) {
            return $response;
        }

        if (! is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;
    }
}
