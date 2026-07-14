<?php

namespace App\Services\ServiceBookings;

use App\Models\UrbanGoodzServiceBookingEvent;
use App\Models\UrbanGoodzServiceProviderEarning;
use App\Models\UrbanGoodzServiceRequest;
use App\Services\UrbanGoodzNotificationService;
use Illuminate\Support\Facades\DB;

class ServiceBookingWorkflow
{
    public function __construct(private UrbanGoodzNotificationService $notifications)
    {
    }

    private const TRANSITIONS = [
        'requested' => ['quoted','accepted','declined','canceled'],
        'quoted' => ['accepted','declined','canceled'],
        'accepted' => ['confirmed','reschedule_requested','canceled'],
        'confirmed' => ['en_route','started','reschedule_requested','canceled'],
        'reschedule_requested' => ['accepted','declined','canceled'],
        'en_route' => ['started','canceled'],
        'started' => ['completed'],
        'completed' => [], 'declined' => [], 'canceled' => [],
    ];

    public function transition(UrbanGoodzServiceRequest $booking, string $to, string $actorType, ?int $actorId, array $metadata = []): UrbanGoodzServiceRequest
    {
        return DB::transaction(function () use ($booking, $to, $actorType, $actorId, $metadata) {
            $locked = UrbanGoodzServiceRequest::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($to, self::TRANSITIONS[$locked->status] ?? [], true), 409, "Illegal service booking transition: {$locked->status} -> {$to}.");
            $from = $locked->status;
            $updates = ['status' => $to];
            if ($to === 'accepted') { $updates['accepted_at'] = now(); }
            if ($to === 'completed') { $updates['completed_at'] = now(); }
            if ($to === 'canceled' && isset($metadata['reason'])) { $updates['cancellation_reason'] = $metadata['reason']; }
            if ($to === 'reschedule_requested' && isset($metadata['requested_start_at'])) { $updates['requested_start_at'] = $metadata['requested_start_at']; }
            $locked->update($updates);
            UrbanGoodzServiceBookingEvent::create(['service_request_id'=>$locked->id,'actor_type'=>$actorType,'actor_id'=>$actorId,'from_status'=>$from,'to_status'=>$to,'metadata'=>$metadata]);
            if ($to === 'completed') { $this->recordEarning($locked); }
            $this->notify($locked, $to);
            return $locked->fresh(['assignedProvider','appointments']);
        });
    }

    private function recordEarning(UrbanGoodzServiceRequest $booking): void
    {
        $gross = (int) $booking->quoted_amount_minor;
        $feeRate = min(max((float) config('service_bookings.platform_fee_percent', 15), 0), 100);
        $fee = (int) round($gross * $feeRate / 100);
        UrbanGoodzServiceProviderEarning::firstOrCreate(['service_request_id'=>$booking->id], ['provider_id'=>$booking->provider_id,'gross_amount_minor'=>$gross,'platform_fee_minor'=>$fee,'provider_amount_minor'=>$gross-$fee,'currency'=>$booking->currency,'status'=>'pending']);
    }

    private function notify(UrbanGoodzServiceRequest $booking, string $status): void
    {
        $payload = ['type'=>'service_booking','booking_id'=>$booking->id,'status'=>$status];
        $this->notifications->notifyCustomer((int) $booking->user_id, 'Service booking updated', 'Your service booking is now '.$status.'.', $payload);
        $this->notifications->notifyVendor((int) $booking->assigned_vendor_id, 'Service booking updated', 'A service booking is now '.$status.'.', $payload);
    }
}
