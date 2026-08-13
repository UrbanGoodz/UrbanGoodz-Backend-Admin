<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceProvider;
use App\Models\UrbanGoodzServiceRequest;
use App\Services\UrbanGoodz\VendorAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookServicesAIController extends Controller
{
    public function __construct(
        private VendorAIService $vendorAI
    ) {}

    // ─── PROVIDER SEARCH ────────────────────────────────────────────────

    /**
     * GET /customer/service-bookings/ai/providers
     * List active providers, optionally filtered by service name, location,
     * category, or budget. Date/time trigger availability ranking.
     */
    public function getProviders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_name' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string'],
        ]);

        $query = UrbanGoodzServiceProvider::where('is_active', true);

        if (!empty($data['service_name'])) {
            $query->where(function ($q) use ($data) {
                $q->where('service_category', 'LIKE', "%{$data['service_name']}%")
                  ->orWhere('business_name', 'LIKE', "%{$data['service_name']}%")
                  ->orWhere('description', 'LIKE', "%{$data['service_name']}%");
            });
        }

        if ($data['location'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->whereJsonContains('service_areas', $data['location'])
                  ->orWhere('service_areas', 'LIKE', "%{$data['location']}%");
            });
        }

        if ($data['category'] ?? false) {
            $query->where('service_category', $data['category']);
        }

        $providers = $query->limit(20)
            ->get()
            ->map(fn($p) => $this->providerSummary($p))
            ->filter()
            ->values()
            ->toArray();

        if (($data['budget_min'] ?? null) || ($data['budget_max'] ?? null)) {
            $providers = array_values(array_filter($providers, function ($p) use ($data) {
                $rate = $p['hourly_rate'] ?? $p['min_budget'] ?? 0;
                if ($data['budget_min'] && $rate < $data['budget_min']) return false;
                if ($data['budget_max'] && $rate > $data['budget_max']) return false;
                return true;
            }));
        }

        if ($data['date'] ?? false) {
            $providers = $this->rankProvidersByAvailability($providers, $data['date'], $data['time'] ?? null);
        }

        return response()->json([
            'success' => true,
            'providers' => $providers,
            'total_found' => count($providers),
        ]);
    }

    /**
     * POST /customer/service-bookings/ai/match
     * Rank active providers against a requested service and return the best match.
     */
    public function matchProviders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string'],
            'location' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string'],
            'preferred_dates' => ['nullable', 'array'],
            'preferred_dates.*' => ['date'],
        ]);

        $query = UrbanGoodzServiceProvider::where('is_active', true)
            ->where(function ($q) use ($data) {
                $q->where('service_category', 'LIKE', "%{$data['service_name']}%")
                  ->orWhere('business_name', 'LIKE', "%{$data['service_name']}%")
                  ->orWhere('description', 'LIKE', "%{$data['service_name']}%");
            });

        if ($data['location'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->whereJsonContains('service_areas', $data['location'])
                  ->orWhere('service_areas', 'LIKE', "%{$data['location']}%");
            });
        }

        if ($data['category'] ?? false) {
            $query->where('service_category', $data['category']);
        }

        $providers = $query->limit(20)
            ->get()
            ->map(fn($p) => $this->providerSummary($p))
            ->filter()
            ->values()
            ->toArray();

        if (($data['budget_min'] ?? null) || ($data['budget_max'] ?? null)) {
            $providers = array_values(array_filter($providers, function ($p) use ($data) {
                $rate = $p['hourly_rate'] ?? $p['min_budget'] ?? 0;
                if ($data['budget_min'] && $rate < $data['budget_min']) return false;
                if ($data['budget_max'] && $rate > $data['budget_max']) return false;
                return true;
            }));
        }

        $preferredDates = $data['preferred_dates'] ?? [];
        $dates = $preferredDates ?: array_filter([$data['date'] ?? null]);
        foreach ($dates as $date) {
            $providers = $this->rankProvidersByAvailability($providers, $date, $data['time'] ?? null);
        }

        $best = $providers[0] ?? null;

        return response()->json([
            'success' => true,
            'best_match' => $best,
            'matches' => $providers,
            'total_found' => count($providers),
        ]);
    }

    private function providerSummary(UrbanGoodzServiceProvider $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->business_name,
            'category' => $p->service_category,
            'rating' => $p->rating,
            'is_verified' => $p->is_verified,
            'service_areas' => $p->service_areas,
            'hourly_rate' => $p->hourly_rate ?? null,
            'min_budget' => $p->min_budget ?? null,
        ];
    }

    public function searchProviders(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string'],
            'location' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'string'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string'],
        ]);

        $query = UrbanGoodzServiceProvider::where('is_active', true);

        $query->where(function ($q) use ($data) {
            $q->where('service_category', 'LIKE', "%{$data['service_name']}%")
              ->orWhere('business_name', 'LIKE', "%{$data['service_name']}%")
              ->orWhere('description', 'LIKE', "%{$data['service_name']}%");
        });

        if ($data['location'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->whereJsonContains('service_areas', $data['location'])
                  ->orWhere('service_areas', 'LIKE', "%{$data['location']}%");
            });
        }

        if ($data['category'] ?? false) {
            $query->where('service_category', $data['category']);
        }

        $providers = $query->limit(20)
            ->get()
            ->map(fn($p) => $this->providerSummary($p))
            ->filter()
            ->values()
            ->toArray();

        // Budget filter
        if (($data['budget_min'] ?? null) || ($data['budget_max'] ?? null)) {
            $providers = array_filter($providers, function ($p) use ($data) {
                $rate = $p['hourly_rate'] ?? $p['min_budget'] ?? 0;
                if ($data['budget_min'] && $rate < $data['budget_min']) return false;
                if ($data['budget_max'] && $rate > $data['budget_max']) return false;
                return true;
            });
        }

        // AI ranking if date/time provided
        if ($data['date'] ?? false) {
            $providers = $this->rankProvidersByAvailability($providers, $data['date'], $data['time'] ?? null);
        }

        return response()->json([
            'success' => true,
            'providers' => array_values($providers),
            'total_found' => count($providers),
        ]);
    }

    private function rankProvidersByAvailability(array $providers, string $date, ?string $time): array
    {
        if (empty($providers)) {
            return $providers;
        }

        $providerIds = array_column($providers, 'id');
        $dayOfWeek = (int) date('w', strtotime($date));
        $requestedTime = $time !== null && $time !== '' ? date('H:i', strtotime($time)) : null;

        $slots = \App\Models\UrbanGoodzProviderAvailability::where('is_active', true)
            ->where('day_of_week', $dayOfWeek)
            ->whereIn('provider_id', $providerIds)
            ->get()
            ->groupBy('provider_id');

        $hasAnySchedule = \App\Models\UrbanGoodzProviderAvailability::where('is_active', true)
            ->whereIn('provider_id', $providerIds)
            ->select('provider_id')
            ->distinct()
            ->pluck('provider_id')
            ->flip();

        foreach ($providers as &$p) {
            $daySlots = $slots->get($p['id'], collect());
            $availableAt = null;
            $score = 0.2;

            if ($daySlots->isNotEmpty()) {
                $score = 0.5;
                $availableAt = $daySlots->min('starts_at');

                if ($requestedTime !== null) {
                    $covering = $daySlots->first(function ($slot) use ($requestedTime) {
                        return $requestedTime >= substr($slot->starts_at, 0, 5)
                            && $requestedTime <= substr($slot->ends_at, 0, 5);
                    });
                    if ($covering) {
                        $score = 1.0;
                        $availableAt = substr($covering->starts_at, 0, 5);
                    }
                }
            } elseif (!isset($hasAnySchedule[$p['id']])) {
                $score = 0.2;
                $availableAt = null;
            } else {
                $score = 0.0;
                $availableAt = null;
            }

            $p['availability_score'] = $score;
            $p['available_at'] = $availableAt ?? ($time ?: 'Flexible');
        }
        unset($p);

        usort($providers, fn($a, $b) => $b['availability_score'] <=> $a['availability_score']);
        return $providers;
    }

    // ─── QUOTE REQUEST ────────────────────────────────────────────────

    public function requestQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'service_name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_time' => ['nullable', 'string'],
            'location' => ['required', 'string'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string'],
            'provider_ids' => ['nullable', 'array'],
            'provider_ids.*' => ['integer'],
        ]);

        $requestedStartAt = null;
        if (!empty($data['preferred_date'])) {
            $time = !empty($data['preferred_time']) ? date('H:i:s', strtotime($data['preferred_time'])) : '09:00:00';
            $requestedStartAt = $data['preferred_date'].' '.$time;
        }

        $serviceRequest = UrbanGoodzServiceRequest::create([
            'user_id' => $data['customer_id'],
            'service_type' => $data['service_name'],
            'description' => $data['description'] ?? null,
            'status' => 'pending',
            'location' => $data['location'],
            'requested_start_at' => $requestedStartAt,
            'provider_id' => $data['provider_ids'][0] ?? null,
            'quoted_amount_minor' => $data['budget_amount'] !== null ? (int) round($data['budget_amount'] * 100) : null,
            'currency' => 'USD',
            'provider_notes' => $data['category'] ?? null,
        ]);

        // If specific providers selected, notify them
        if (!empty($data['provider_ids'])) {
            $this->notifyProviders($data['provider_ids'], $serviceRequest->id, $data);
        }

        return response()->json([
            'success' => true,
            'request_id' => $serviceRequest->id,
            'request_number' => $serviceRequest->id,
            'message' => 'Quote request sent to providers.',
        ]);
    }

    private function notifyProviders(array $providerIds, int $requestId, array $data): void
    {
        $serviceRequest = UrbanGoodzServiceRequest::find($requestId);

        foreach ($providerIds as $pid) {
            $provider = \App\Models\UrbanGoodzServiceProvider::find($pid);
            if (!$provider) {
                continue;
            }

            if ($serviceRequest) {
                \App\Models\UrbanGoodzServiceBookingEvent::create([
                    'service_request_id' => $serviceRequest->id,
                    'actor_type' => 'customer',
                    'actor_id' => $serviceRequest->user_id,
                    'from_status' => null,
                    'to_status' => $serviceRequest->status,
                    'metadata' => [
                        'type' => 'provider_notified',
                        'provider_id' => $pid,
                        'provider_name' => $provider->business_name,
                        'service_request_id' => $serviceRequest->id,
                    ],
                ]);
            }
        }
    }

    // ─── AVAILABILITY CHECK ────────────────────────────────────────────

    public function checkAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'string'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:12'],
        ]);

        $provider = UrbanGoodzServiceProvider::findOrFail($data['provider_id']);
        $dayOfWeek = (int) date('w', strtotime($data['date']));
        $requestedTime = $data['time'] !== null && $data['time'] !== '' ? date('H:i', strtotime($data['time'])) : null;

        $daySlots = UrbanGoodzProviderAvailability::where('provider_id', $data['provider_id'])
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        $available = false;
        if ($requestedTime !== null) {
            $available = $daySlots->contains(function ($slot) use ($requestedTime) {
                return $requestedTime >= substr($slot->starts_at, 0, 5)
                    && $requestedTime <= substr($slot->ends_at, 0, 5);
            });
        } else {
            $available = $daySlots->isNotEmpty();
        }

        // Check existing bookings
        $booked = UrbanGoodzServiceRequest::where('provider_id', $data['provider_id'])
            ->whereDate('scheduled_at', $data['date'])
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        return response()->json([
            'success' => true,
            'provider_id' => $provider->id,
            'provider_name' => $provider->business_name,
            'date' => $data['date'],
            'is_available' => $available && !$booked,
            'booked' => $booked,
            'available_slots' => $this->getAvailableSlots($provider, $data['date'], $daySlots),
        ]);
    }

    private function getAvailableSlots(UrbanGoodzServiceProvider $provider, string $date, $daySlots = null): array
    {
        if ($daySlots !== null && $daySlots->isNotEmpty()) {
            return $daySlots->map(fn($slot) => [
                'start' => substr($slot->starts_at, 0, 5),
                'end' => substr($slot->ends_at, 0, 5),
            ])->values()->toArray();
        }

        // Fallback: derive slots for the requested day from the provider's weekly schedule
        $dayOfWeek = (int) date('w', strtotime($date));
        $slots = UrbanGoodzProviderAvailability::where('provider_id', $provider->id)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->get();

        if ($slots->isNotEmpty()) {
            return $slots->map(fn($slot) => [
                'start' => substr($slot->starts_at, 0, 5),
                'end' => substr($slot->ends_at, 0, 5),
            ])->values()->toArray();
        }

        return [];
    }

    // ─── BUDGET FILTER ────────────────────────────────────────────────

    public function filterByBudget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'service_name' => ['required', 'string'],
            'location' => ['nullable', 'string'],
            'max_budget' => ['required', 'numeric', 'min:0'],
            'date' => ['nullable', 'date'],
        ]);

        $query = UrbanGoodzServiceProvider::where('is_active', true);

        $query->where(function ($q) use ($data) {
            $q->where('service_category', 'LIKE', "%{$data['service_name']}%")
              ->orWhere('business_name', 'LIKE', "%{$data['service_name']}%");
        });

        if ($data['location'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->whereJsonContains('service_areas', $data['location'])
                  ->orWhere('service_areas', 'LIKE', "%{$data['location']}%");
            });
        }

        $providers = $query->get()
            ->filter(function ($p) use ($data) {
                $rate = $p->hourly_rate ?? $p->min_budget ?? PHP_INT_MAX;
                return $rate <= $data['max_budget'];
            })
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->business_name,
                'category' => $p->service_category,
                'rating' => $p->rating,
                'hourly_rate' => $p->hourly_rate,
                'min_budget' => $p->min_budget,
                'estimated_total' => $p->hourly_rate ? $p->hourly_rate * 3 : null, // 3hr default
            ])
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'max_budget' => $data['max_budget'],
            'providers' => $providers,
        ]);
    }

    // ─── QUOTE COMPARISON ─────────────────────────────────────────────

    public function compareQuotes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'quotes' => ['required', 'array', 'min:2'],
            'quotes.*.provider_id' => ['required', 'integer'],
            'quotes.*.amount' => ['required', 'numeric', 'min:0'],
            'quotes.*.details' => ['nullable', 'string'],
            'quotes.*.includes' => ['nullable', 'array'],
        ]);

        $quotes = $data['quotes'];
        $providers = UrbanGoodzServiceProvider::whereIn('id', array_column($quotes, 'provider_id'))->get()->keyBy('id');

        $enriched = array_map(function ($q) use ($providers) {
            $p = $providers->get($q['provider_id']);
            return [
                'provider_id' => $q['provider_id'],
                'provider_name' => $p->business_name ?? 'Unknown',
                'provider_rating' => $p->rating ?? 0,
                'amount' => $q['amount'],
                'details' => $q['details'] ?? '',
                'includes' => $q['includes'] ?? [],
                'value_score' => $this->calculateValueScore($q, $p),
            ];
        }, $quotes);

        usort($enriched, fn($a, $b) => $b['value_score'] <=> $a['value_score']);

        return response()->json([
            'success' => true,
            'comparison' => [
                'best_value' => $enriched[0] ?? null,
                'cheapest' => collect($enriched)->sortBy('amount')->first(),
                'all' => $enriched,
                'summary' => [
                    'lowest' => min(array_column($quotes, 'amount')),
                    'highest' => max(array_column($quotes, 'amount')),
                    'average' => array_sum(array_column($quotes, 'amount')) / count($quotes),
                    'spread' => max(array_column($quotes, 'amount')) - min(array_column($quotes, 'amount')),
                ],
            ],
        ]);
    }

    private function calculateValueScore(array $quote, ?UrbanGoodzServiceProvider $provider): float
    {
        $score = 50;
        if ($provider) {
            $score += ($provider->rating ?? 0) * 10; // up to 50
            if ($provider->is_verified) $score += 10;
        }
        // Lower price = higher value (inverse)
        $avg = collect($quote['amount'])->avg() ?? 1;
        $score += max(0, 20 * (1 - $quote['amount'] / $avg));
        return round($score, 1);
    }

    // ─── CANCELLATION REPLACEMENT ──────────────────────────────────────

    public function findReplacement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cancelled_request_id' => ['required', 'integer'],
            'reason' => ['nullable', 'string'],
        ]);

        $cancelled = UrbanGoodzServiceRequest::findOrFail($data['cancelled_request_id']);

        $query = UrbanGoodzServiceProvider::where('is_active', true)
            ->where(function ($q) use ($cancelled) {
                $q->where('service_category', 'LIKE', "%{$cancelled->service_type}%")
                  ->orWhere('description', 'LIKE', "%{$cancelled->service_type}%");
            });

        if ($cancelled->location) {
            $query->where(function ($q) use ($cancelled) {
                $q->whereJsonContains('service_areas', $cancelled->location)
                  ->orWhere('service_areas', 'LIKE', "%{$cancelled->location}%");
            });
        }

        $replacements = $query->limit(10)
            ->get()
            ->map(fn($p) => $this->providerSummary($p))
            ->filter()
            ->values()
            ->toArray();

        if ($cancelled->requested_start_at) {
            $replacements = $this->rankProvidersByAvailability(
                $replacements,
                $cancelled->requested_start_at->toDateString(),
                $cancelled->requested_start_at->format('H:i')
            );
        }

        return response()->json([
            'success' => true,
            'cancelled_request' => [
                'id' => $cancelled->id,
                'service_type' => $cancelled->service_type,
                'scheduled_at' => $cancelled->scheduled_at?->toDateTimeString(),
            ],
            'replacement' => $replacements[0] ?? null,
            'alternatives' => array_slice($replacements, 1, 3),
        ]);
    }

    // ─── REMINDERS ────────────────────────────────────────────────────

    public function getReminders(Request $request): JsonResponse
    {
        $userId = (int) ($request->input('user_id') ?? auth('api')->id());

        $upcoming = UrbanGoodzServiceRequest::where('user_id', $userId)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', now())
            ->where('scheduled_at', '<=', now()->addDays(7))
            ->with('assignedProvider')
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn($r) => [
                'request_id' => $r->id,
                'service_type' => $r->service_type,
                'provider' => $r->assignedProvider->business_name ?? 'Unknown',
                'scheduled_at' => $r->scheduled_at?->toDateTimeString(),
                'location' => $r->location,
                'status' => $r->status,
                'days_until' => $r->scheduled_at ? now()->diffInDays($r->scheduled_at, false) : null,
            ])
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'reminders' => $upcoming,
        ]);
    }

    // ─── COMPLETION VERIFICATION ──────────────────────────────────────

    public function verifyCompletion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer'],
            'photo' => ['nullable', 'string'], // base64
            'signature' => ['nullable', 'string'],
            'customer_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'notes' => ['nullable', 'string'],
        ]);

        $serviceRequest = UrbanGoodzServiceRequest::findOrFail($data['request_id']);

        if ($serviceRequest->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Service already marked complete.',
            ], 409);
        }

        $serviceRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
            'provider_notes' => $data['notes'] ?? $serviceRequest->provider_notes,
        ]);

        if (!empty($data['customer_rating']) && $serviceRequest->provider_id && $serviceRequest->user_id) {
            \App\Models\UrbanGoodzServiceReview::updateOrCreate(
                ['service_request_id' => $serviceRequest->id],
                [
                    'provider_id' => $serviceRequest->provider_id,
                    'user_id' => $serviceRequest->user_id,
                    'rating' => $data['customer_rating'],
                    'comment' => $data['notes'] ?? null,
                ]
            );
        }

        // Trigger payout (uses real provider id, not vendor profile id)
        if ($serviceRequest->provider_id) {
            \App\Models\UrbanGoodzServiceProviderEarning::updateOrCreate(
                ['service_request_id' => $serviceRequest->id],
                [
                    'provider_id' => $serviceRequest->provider_id,
                    'gross_amount_minor' => $serviceRequest->quoted_amount_minor ?? 0,
                    'platform_fee_minor' => 0,
                    'provider_amount_minor' => $serviceRequest->quoted_amount_minor ?? 0,
                    'currency' => $serviceRequest->currency ?? 'USD',
                    'status' => 'pending',
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Service marked complete.',
            'request_id' => $serviceRequest->id,
        ]);
    }

    // ─── VENDOR AI ─────────────────────────────────────────────────────

    public function estimatePrepTime(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'store_type' => ['nullable', 'string'],
        ]);

        $result = $this->vendorAI->estimatePrepTime($data['items'], $data['store_type'] ?? 'restaurant');

        return response()->json($result);
    }

    public function generateAlerts(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);
        $result = $this->vendorAI->generateVendorAlerts($vendor->id);

        return response()->json($result);
    }

    public function analyzePerformance(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);
        $result = $this->vendorAI->analyzeVendorPerformance($vendor->id);

        return response()->json($result);
    }

    public function suggestPromotions(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);
        $result = $this->vendorAI->suggestVendorPromotions($vendor->id);

        return response()->json($result);
    }

    public function generateDailyBrief(Request $request): JsonResponse
    {
        $vendor = $this->resolveVendor($request);
        $result = $this->vendorAI->generateVendorDailyBrief($vendor->id);

        return response()->json($result);
    }

    private function resolveVendor(Request $request): \App\Models\Vendor
    {
        $vendor = $request['vendor'] ?? $request->user('vendor');

        abort_unless($vendor, 401, 'Unauthorized.');
        abort_unless($vendor instanceof \App\Models\Vendor, 403, 'Vendor profile not found.');

        return $vendor;
    }
}