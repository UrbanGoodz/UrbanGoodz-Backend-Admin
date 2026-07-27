<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class UrbanGoodzRealtimeUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    private const ACCOUNT_TYPES = [
        'shopper',
        'vendor',
        'driver',
        'business',
        'dispatcher',
        'admin',
    ];

    private function __construct(
        private readonly string $channel,
        private readonly string $event,
        public readonly array $payload
    ) {
    }

    public static function shopperOrder(
        int $customerId,
        int $orderId,
        string $status
    ): self {
        return new self(
            "ug.shopper.{$customerId}.orders",
            'order.status.updated',
            ['order_id' => $orderId, 'status' => $status]
        );
    }

    public static function vendorOrder(
        int $vendorId,
        int $orderId,
        string $status
    ): self {
        return new self(
            "ug.vendor.{$vendorId}.orders",
            'vendor.order.updated',
            ['order_id' => $orderId, 'status' => $status]
        );
    }

    public static function driverAssignment(
        int $deliveryManId,
        string $assignmentType,
        int $assignmentId,
        string $status
    ): self {
        return new self(
            "ug.driver.{$deliveryManId}.assignments",
            'driver.assignment.updated',
            [
                'assignment_type' => $assignmentType,
                'assignment_id' => $assignmentId,
                'status' => $status,
            ]
        );
    }

    public static function businessRoute(
        int $businessClientId,
        int $routeId,
        string $status
    ): self {
        return new self(
            "ug.business.{$businessClientId}.routes",
            'business.route.updated',
            ['route_id' => $routeId, 'status' => $status]
        );
    }

    public static function dispatcherLoad(
        int $dispatcherId,
        int $loadId,
        string $status
    ): self {
        return new self(
            "ug.dispatcher.{$dispatcherId}.loads",
            'dispatcher.load.updated',
            ['load_id' => $loadId, 'status' => $status]
        );
    }

    public static function paymentStatus(
        string $accountType,
        int $accountId,
        int $transactionId,
        string $status
    ): self {
        if (! in_array($accountType, self::ACCOUNT_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported realtime payment account type.');
        }

        return new self(
            "ug.payment.{$accountType}.{$accountId}.statuses",
            'payment.status.updated',
            ['transaction_id' => $transactionId, 'status' => $status]
        );
    }

    public static function supportMessage(
        int $conversationId,
        int $messageId
    ): self {
        return new self(
            "ug.support.{$conversationId}",
            'support.message.created',
            ['conversation_id' => $conversationId, 'message_id' => $messageId]
        );
    }

    public static function adminOperation(
        string $resourceType,
        int $resourceId,
        string $action
    ): self {
        return new self(
            'ug.admin.operations',
            'admin.operation.updated',
            [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'action' => $action,
            ]
        );
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel($this->channel)];
    }

    public function broadcastAs(): string
    {
        return $this->event;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
