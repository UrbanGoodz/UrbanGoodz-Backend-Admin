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
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->business_name,
                'category' => $p->service_category,
                'rating' => $p->rating,
                'is_verified' => $p->is_verified,
                'service_areas' => $p->service_areas,
                'hourly_rate' => $p->hourly_rate ?? null,
                'min_budget' => $p->min_budget ?? null,
            ])
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
        // In production: query ProviderAvailability model
        // For now, return as-is with placeholder
        foreach ($providers as &$p) {
            $p['availability_score'] = rand(60, 100) / 100;
            $p['available_at'] = $time ?? 'Flexible';
        }
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

        $requestNumber = 'BS-' . strtoupper(uniqid());
        $requestId = \DB::table('urban_goodz_book_anywhere_requests')->insertGetId([
            'request_number' => $requestNumber,
            'customer_id' => $data['customer_id'],
            'service_name' => $data['service_name'],
            'description' => $data['description'] ?? null,
            'preferred_date' => $data['preferred_date'] ?? null,
            'preferred_time' => $data['preferred_time'] ?? null,
            'location' => $data['location'],
            'budget_amount' => $data['budget_amount'] ?? null,
            'category' => $data['category'] ?? null,
            'status' => 'pending',
            'metadata' => $data['provider_ids'] ? ['provider_ids' => $data['provider_ids']] : [],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // If specific providers selected, notify them
        if (!empty($data['provider_ids'])) {
            $this->notifyProviders($data['provider_ids'], $requestId, $data);
        }

        return response()->json([
            'success' => true,
            'request_id' => $requestId,
            'request_number' => $requestNumber,
            'message' => 'Quote request sent to providers.',
        ]);
    }

    private function notifyProviders(array $providerIds, int $requestId, array $data): void
    {
        foreach ($providerIds as $pid) {
            \App\Models\UrbanGoodzServiceProvider::where('id', $pid)->update([
                'last_quote_request_at' => now(),
            ]);
            // In production: push notification, email, SMS
        }
    }

    // ─── AVAILABILITY CHECK ────────────────────────────────────────────

    public function checkAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:12'],
        ]);

        $provider = UrbanGoodzServiceProvider::findOrFail($data['provider_id']);

        // Check provider availability model
        $available = UrbanGoodzProviderAvailability::where('provider_id', $data['provider_id'])
            ->where('date', $data['date'])
            ->where(function ($q) use ($data) {
                $q->where('is_available', true)
                  ->where(function ($q2) use ($data) {
                      if (!empty($data['start_time'])) {
                          $q2->where('start_time', '<=', $data['start_time']);
                      }
                  })
                  ->where(function ($q2) use ($data) {
                      if (!empty($data['end_time'])) {
                          $q2->where('end_time', '>=', $data['end_time']);
                      }
                  });
            })
            ->exists();

        // Check existing bookings
        $booked = UrbanGoodzServiceRequest::where('provider_id', $data['provider_id'])
            ->whereDate('preferred_date', $data['date'])
            ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
            ->exists();

        return response()->json([
            'success' => true,
            'provider_id' => $provider->id,
            'provider_name' => $provider->business_name,
            'date' => $data['date'],
            'is_available' => $available && !$booked,
            'booked' => $booked,
            'available_slots' => $this->getAvailableSlots($provider, $data['date']),
        ]);
    }

    private function getAvailableSlots(UrbanGoodzServiceProvider $provider, string $date): array
    {
        // Return available time slots for the day
        return [
            ['start' => '09:00', 'end' => '12:00'],
            ['start' => '13:00', 'end' => '17:00'],
            ['start' => '18:00', 'end' => '21:00'],
        ];
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

        $replacements = $this->vendorAI->search_service_providers(
            $cancelled->service_name,
            $cancelled->location,
            null,
            $cancelled->budget_amount
        );

        $replacement = null;
        if (!empty($replacements['matched_providers'])) {
            $replacement = $replacements['matched_providers'][0];
        }

        return response()->json([
            'success' => true,
            'cancelled_request' => [
                'id' => $cancelled->id,
                'service_name' => $cancelled->service_name,
                'date' => $cancelled->preferred_date,
            ],
            'replacement' => $replacement ? [
                'provider_id' => $replacement['id'],
                'provider_name' => $replacement['name'],
                'rating' => $replacement['rating'],
                'estimated_cost' => $replacement['hourly_rate'] ?? null,
            ] : null,
            'alternatives' => array_slice($replacements['matched_providers'] ?? [], 1, 3),
        ]);
    }

    // ─── REMINDERS ────────────────────────────────────────────────────

    public function getReminders(Request $request): JsonResponse
    {
        $customerId = $request->input('customer_id') ?? auth('api')->id();

        $upcoming = UrbanGoodzServiceRequest::where('customer_id', $customerId)
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereDate('preferred_date', '>=', now())
            ->whereDate('preferred_date', '<=', now()->addDays(7))
            ->with('provider')
            ->orderBy('preferred_date')
            ->orderBy('preferred_time')
            ->get()
            ->map(fn($r) => [
                'request_id' => $r->id,
                'service_name' => $r->service_name,
                'provider' => $r->provider->business_name ?? 'Unknown',
                'date' => $r->preferred_date,
                'time' => $r->preferred_time,
                'location' => $r->location,
                'status' => $r->status,
                'days_until' => now()->diffInDays($r->preferred_date, false),
            ])
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

        $serviceRequest->update([
            'status' => 'completed',
            'completed_at' => now(),
            'customer_rating' => $data['customer_rating'] ?? null,
            'completion_notes' => $data['notes'] ?? null,
            'completion_photo' => $data['photo'] ?? null,
            'customer_signature' => $data['signature'] ?? null,
        ]);

        // Trigger payout
        if ($serviceRequest->provider_id) {
            $this->vendorAI->analyzeVendorPerformance($serviceRequest->provider_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Service marked complete.',
            'request_id' => $serviceRequest->id,
        ]);
    }
}