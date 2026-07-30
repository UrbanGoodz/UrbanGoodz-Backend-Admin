<?php

namespace App\Services\ServiceBookings;

use App\Models\UrbanGoodzProviderService;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ServiceBookingAvailabilityService
{
    public function assertAvailable(
        UrbanGoodzServiceProvider $provider,
        UrbanGoodzProviderService $service,
        Carbon $start,
        ?int $ignoreBookingId = null
    ): Carbon {
        $end = $start->copy()->addMinutes((int) $service->duration_minutes);

        $insideWorkingHours = $provider->availability()
            ->where('is_active', true)
            ->get()
            ->contains(function ($window) use ($start, $service) {
                $localStart = $start->copy()->setTimezone($window->timezone);
                $localEnd = $localStart->copy()->addMinutes((int) $service->duration_minutes);

                return $localStart->dayOfWeek === (int) $window->day_of_week
                    && $window->starts_at <= $localStart->format('H:i:s')
                    && $window->ends_at >= $localEnd->format('H:i:s');
            });
        abort_unless($insideWorkingHours, 422, 'Requested time is outside provider availability.');

        $conflict = UrbanGoodzServiceRequest::query()
            ->where('provider_id', $provider->id)
            ->whereIn('status', ['accepted', 'confirmed', 'en_route', 'started'])
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->where('scheduled_at', '<', $end)
            ->where(function ($query) use ($start) {
                $query->where('scheduled_end_at', '>', $start)
                    ->orWhere(function ($fallback) use ($start) {
                        $fallback->whereNull('scheduled_end_at')->where('scheduled_at', '>=', $start);
                    });
            })
            ->exists();
        abort_if($conflict, 409, 'Requested time is no longer available.');

        return $end;
    }

    public function slots(
        UrbanGoodzServiceProvider $provider,
        UrbanGoodzProviderService $service,
        Carbon $from,
        Carbon $until
    ): array {
        $slots = [];
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $until->copy()->endOfDay()) as $date) {
            foreach ($provider->availability()->where('day_of_week', $date->dayOfWeek)->where('is_active', true)->get() as $window) {
                $cursor = Carbon::parse($date->format('Y-m-d').' '.$window->starts_at, $window->timezone);
                $windowEnd = Carbon::parse($date->format('Y-m-d').' '.$window->ends_at, $window->timezone);
                while ($cursor->copy()->addMinutes($service->duration_minutes)->lte($windowEnd)) {
                    if ($cursor->isFuture()) {
                        try {
                            $this->assertAvailable($provider, $service, $cursor);
                            $slots[] = [
                                'starts_at' => $cursor->toIso8601String(),
                                'ends_at' => $cursor->copy()->addMinutes($service->duration_minutes)->toIso8601String(),
                            ];
                        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                            // Occupied slots are omitted from discovery.
                        }
                    }
                    $cursor->addMinutes(max(15, min(60, (int) $service->duration_minutes)));
                }
            }
        }

        return $slots;
    }
}
