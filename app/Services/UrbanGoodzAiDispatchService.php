<?php

namespace App\Services;

use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Models\UrbanGoodzBusinessClient;
use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UrbanGoodzAiDispatchService
{
    public function __construct(
        private UrbanGoodzNotificationService $notificationService,
        private UrbanGoodzDriverDispatchNotificationService $dispatchNotificationService
    ) {}

    public function createDraft(array $data): AiDispatch
    {
        $data['uuid'] = $data['uuid'] ?? (string) Str::uuid();
        return AiDispatch::create($data);
    }

    public function createDispatch(array $data): AiDispatch
    {
        return $this->createAndSend($data);
    }

    public function sendToDriver(AiDispatch $dispatch): AiDispatch
    {
        DB::transaction(function () use ($dispatch) {
            $dispatch->sendToDriver();
            $this->pushToDriver($dispatch);
        });
        return $dispatch->fresh();
    }

    public function createAndSend(array $data): AiDispatch
    {
        return DB::transaction(function () use ($data) {
            $dispatch = $this->createDraft($data);
            $dispatch->approve();
            $this->sendToDriver($dispatch);
            return $dispatch;
        });
    }

    public function pushToDriver(AiDispatch $dispatch): void
    {
        $dm = DeliveryMan::find($dispatch->delivery_man_id);
        if (!$dm) {
            Log::warning('AiDispatch push failed: driver not found', ['dispatch_id' => $dispatch->id]);
            $dispatch->update(['push_status' => 'failed', 'push_error' => 'Driver not found']);
            return;
        }

        if ($dm->fcm_token) {
            $data = [
                'type' => 'ai_dispatch',
                'dispatch_id' => $dispatch->id,
                'load_id' => $dispatch->load_id,
                'route_id' => $dispatch->route_id,
                'order_id' => $dispatch->order_id,
                'offer_expires_at' => $dispatch->offer_expires_at?->toIso8601String(),
                'title' => 'New dispatch offer',
                'description' => 'You have a new dispatch offer. Tap to review.',
            ];

            try {
                Helpers::send_push_notif_to_device($dm->fcm_token, $data);
                $dispatch->update(['push_sent' => true, 'push_status' => 'sent']);
            } catch (\Throwable $e) {
                Log::error('AiDispatch push failed: ' . $e->getMessage(), ['dispatch_id' => $dispatch->id]);
                $dispatch->update(['push_status' => 'failed', 'push_error' => $e->getMessage()]);
            }
        } else {
            Log::info('AiDispatch push skipped: driver has no FCM token', ['dispatch_id' => $dispatch->id]);
            $dispatch->update(['push_status' => 'skipped_no_token']);
        }

        try {
            $this->dispatchNotificationService->createForDriver(
                $dispatch->delivery_man_id,
                'ai_dispatch',
                'New dispatch offer',
                "Dispatch #{$dispatch->id} is available for review.",
                [
                    'type' => 'ai_dispatch',
                    'dispatch_id' => $dispatch->id,
                    'load_id' => $dispatch->load_id,
                    'route_id' => $dispatch->route_id,
                    'requires_action' => true,
                ],
                'ai_dispatch:' . $dispatch->id
            );
            $dispatch->update(['in_app_notified' => true]);
        } catch (\Exception $e) {
            Log::warning('AiDispatch in-app notification failed: ' . $e->getMessage());
        }
    }

    public function expireStaleOffers(int $maxMinutes = 30): int
    {
        $cutoff = now()->subMinutes($maxMinutes);
        return AiDispatch::whereIn('status', [AiDispatch::STATUS_PENDING_DRIVER, AiDispatch::STATUS_SENT])
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('offer_expires_at')
                  ->where('sent_at', '<', $cutoff);
            })
            ->orWhere(function ($q) {
                $q->whereNotNull('offer_expires_at')
                  ->where('offer_expires_at', '<', now());
            })
            ->update(['status' => AiDispatch::STATUS_EXPIRED, 'expired_at' => now()]);
    }

    public function getDriverDispatches(int $driverId, ?string $statusFilter = null, int $perPage = 20)
    {
        $query = AiDispatch::forDriver($driverId)
            ->with(['load', 'route', 'businessClient' => function ($q) {
                $q->select('id', 'company_name');
            }]);

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        return $query->latest()->paginate($perPage);
    }

    public function getDriverPerformanceSummary(int $driverId): array
    {
        $total = AiDispatch::forDriver($driverId)->count();
        $accepted = AiDispatch::forDriver($driverId)->where('status', AiDispatch::STATUS_ACCEPTED)->count();
        $declined = AiDispatch::forDriver($driverId)->where('status', AiDispatch::STATUS_DECLINED)->count();
        $expired = AiDispatch::forDriver($driverId)->where('status', AiDispatch::STATUS_EXPIRED)->count();
        $completed = AiDispatch::forDriver($driverId)->where('status', AiDispatch::STATUS_DELIVERED)->count();
        $exceptions = AiDispatch::forDriver($driverId)->where('exception_state', 'open')->count();
        $onTime = AiDispatch::forDriver($driverId)
            ->where('status', AiDispatch::STATUS_DELIVERED)
            ->where('delivered_at', '<=', DB::raw('offer_expires_at'))
            ->count();

        return [
            'total_offers' => $total,
            'accepted' => $accepted,
            'declined' => $declined,
            'expired' => $expired,
            'completed' => $completed,
            'exceptions' => $exceptions,
            'on_time_deliveries' => $onTime,
            'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 1) : 0,
            'completion_rate' => $accepted > 0 ? round(($completed / $accepted) * 100, 1) : 0,
            'on_time_rate' => $completed > 0 ? round(($onTime / $completed) * 100, 1) : 0,
        ];
    }

    public function hasActiveDispatch(int $driverId, ?int $excludeId = null): bool
    {
        $inProgressStatuses = [
            AiDispatch::STATUS_ACCEPTED,
            AiDispatch::STATUS_EN_ROUTE_TO_PICKUP,
            AiDispatch::STATUS_ARRIVED_AT_PICKUP,
            AiDispatch::STATUS_PICKED_UP,
            AiDispatch::STATUS_IN_TRANSIT,
            AiDispatch::STATUS_ARRIVED_AT_DELIVERY,
        ];
        $query = AiDispatch::forDriver($driverId)->whereIn('status', $inProgressStatuses);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }

    public function getBusinessDispatches(int $clientId, ?string $statusFilter = null, int $perPage = 20)
    {
        $query = AiDispatch::forBusinessClient($clientId)
            ->with(['deliveryMan', 'load', 'route']);

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        return $query->latest()->paginate($perPage);
    }
}
