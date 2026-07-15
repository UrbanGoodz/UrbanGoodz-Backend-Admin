<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzRentalAsset;
use App\Models\UrbanGoodzRentalBooking;
use App\Services\UrbanGoodz\VendorAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RentalAIController extends Controller
{
    public function __construct(
        private VendorAIService $vendorAI
    ) {}

    // ─── ASSET SEARCH ──────────────────────────────────────────────────

    public function searchAssets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_type' => ['nullable', 'string'], // car, van, truck, suv, motorcycle, trailer
            'make' => ['nullable', 'string'],
            'model' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'passengers' => ['nullable', 'integer', 'min:1', 'max:50'],
            'max_daily_rate' => ['nullable', 'numeric', 'min:0'],
            'features' => ['nullable', 'array'], // ac, gps, bluetooth, etc.
            'transmission' => ['nullable', 'string', 'in:automatic,manual'],
            'fuel_type' => ['nullable', 'string', 'in:gas,diesel,electric,hybrid'],
        ]);

        $query = UrbanGoodzRentalAsset::where('is_active', true)
            ->where('status', 'available');

        if ($data['asset_type'] ?? false) {
            $query->where('asset_type', $data['asset_type']);
        }
        if ($data['make'] ?? false) {
            $query->where('make', 'LIKE', "%{$data['make']}%");
        }
        if ($data['model'] ?? false) {
            $query->where('model', 'LIKE', "%{$data['model']}%");
        }
        if ($data['location'] ?? false) {
            $query->where(function ($q) use ($data) {
                $q->where('pickup_location', 'LIKE', "%{$data['location']}%")
                  ->orWhere('return_location', 'LIKE', "%{$data['location']}%");
            });
        }
        if ($data['passengers'] ?? false) {
            $query->where('passenger_capacity', '>=', $data['passengers']);
        }
        if ($data['max_daily_rate'] ?? false) {
            $query->where('daily_rate', '<=', $data['max_daily_rate']);
        }
        if ($data['features'] ?? false) {
            foreach ($data['features'] as $feature) {
                $query->whereJsonContains('features', $feature);
            }
        }
        if ($data['transmission'] ?? false) {
            $query->where('transmission', $data['transmission']);
        }
        if ($data['fuel_type'] ?? false) {
            $query->where('fuel_type', $data['fuel_type']);
        }

        // Check availability for date range
        $availableIds = UrbanGoodzRentalBooking::where('status', 'confirmed')
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                  ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                  ->orWhere(function ($q2) use ($data) {
                      $q2->where('start_date', '<=', $data['start_date'])
                         ->where('end_date', '>=', $data['end_date']);
                  });
            })
            ->pluck('rental_asset_id')
            ->toArray();

        $query->whereNotIn('id', $availableIds);

        $assets = $query->limit(20)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'asset_type' => $a->asset_type,
                'make' => $a->make,
                'model' => $a->model,
                'year' => $a->year,
                'daily_rate' => '$' . number_format($a->daily_rate, 2),
                'hourly_rate' => $a->hourly_rate ? '$' . number_format($a->hourly_rate, 2) : null,
                'deposit_amount' => '$' . number_format($a->deposit_amount, 2),
                'passenger_capacity' => $a->passenger_capacity,
                'pickup_location' => $a->pickup_location,
                'return_location' => $a->return_location,
                'features' => $a->features,
                'transmission' => $a->transmission,
                'fuel_type' => $a->fuel_type,
                'images' => $a->images,
            ])
            ->toArray();

        // Calculate estimated total for date range
        $days = \Carbon\Carbon::parse($data['start_date'])->diffInDays(\Carbon\Carbon::parse($data['end_date'])) + 1;
        foreach ($assets as &$asset) {
            $rate = (float)str_replace(['$', ','], '', $asset['daily_rate']);
            $asset['estimated_total'] = '$' . number_format($rate * $days, 2);
            $asset['rental_days'] = $days;
        }

        return response()->json([
            'success' => true,
            'assets' => $assets,
            'search_criteria' => $data,
            'total_found' => count($assets),
        ]);
    }

    // ─── INTELLIGENT MATCHING ──────────────────────────────────────────

    public function matchAssets(Request $request): JsonResponse
    {
        $data = $request->validate([
            'requirements' => ['required', 'array'],
            'requirements.asset_type' => ['nullable', 'string'],
            'requirements.passengers' => ['nullable', 'integer', 'min:1'],
            'requirements.luggage' => ['nullable', 'string'], // small, medium, large
            'requirements.terrain' => ['nullable', 'string'], // city, highway, offroad
            'requirements.budget_per_day' => ['nullable', 'numeric', 'min:0'],
            'requirements.features' => ['nullable', 'array'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $req = $data['requirements'];
        $start = $data['start_date'];
        $end = $data['end_date'];

        $query = UrbanGoodzRentalAsset::where('is_active', true)
            ->where('status', 'available');

        if ($req['asset_type'] ?? false) {
            $query->where('asset_type', $req['asset_type']);
        }

        if ($req['passengers'] ?? false) {
            $query->where('passenger_capacity', '>=', $req['passengers']);
        }

        if ($req['budget_per_day'] ?? false) {
            $query->where('daily_rate', '<=', $req['budget_per_day']);
        }

        // Luggage space inference
        if ($req['luggage'] ?? false) {
            $minCargo = match($req['luggage']) {
                'small' => 10, // cubic feet
                'medium' => 20,
                'large' => 35,
                default => 0,
            };
            if ($minCargo > 0) {
                $query->where('cargo_capacity_cuft', '>=', $minCargo);
            }
        }

        // Terrain inference
        if ($req['terrain'] ?? false) {
            $query->where(function ($q) use ($req) {
                if ($req['terrain'] === 'offroad') {
                    $q->whereIn('asset_type', ['suv', 'truck'])
                      ->orWhereJsonContains('features', '4wd')
                      ->orWhereJsonContains('features', 'awd');
                } elseif ($req['terrain'] === 'highway') {
                    $q->whereJsonContains('features', 'cruise_control')
                      ->orWhere('fuel_type', 'hybrid');
                }
            });
        }

        if ($req['features'] ?? false) {
            foreach ($req['features'] as $feature) {
                $query->whereJsonContains('features', $feature);
            }
        }

        // Check availability
        $bookedIds = UrbanGoodzRentalBooking::where('status', 'confirmed')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('start_date', [$start, $end])
                  ->orWhereBetween('end_date', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->where('start_date', '<=', $start)
                         ->where('end_date', '>=', $end);
                  });
            })
            ->pluck('rental_asset_id')
            ->toArray();

        $query->whereNotIn('id', $bookedIds);

        $assets = $query->limit(10)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'asset_type' => $a->asset_type,
                'make' => $a->make,
                'model' => $a->model,
                'year' => $a->year,
                'daily_rate' => $a->daily_rate,
                'deposit_amount' => $a->deposit_amount,
                'passenger_capacity' => $a->passenger_capacity,
                'cargo_capacity' => $a->cargo_capacity_cuft ?? null,
                'features' => $a->features,
                'match_score' => $this->calculateMatchScore($a, $req),
                'match_reasons' => $this->getMatchReasons($a, $req),
            ])
            ->sortByDesc('match_score')
            ->values()
            ->toArray();

        return response()->json([
            'success' => true,
            'matched_assets' => $assets,
            'requirements' => $req,
        ]);
    }

    private function calculateMatchScore(UrbanGoodzRentalAsset $asset, array $req): float
    {
        $score = 0;
        $factors = 0;

        if ($req['passengers'] ?? false) {
            $factors++;
            if ($asset->passenger_capacity >= $req['passengers']) $score += 30;
        }
        if ($req['budget_per_day'] ?? false) {
            $factors++;
            if ($asset->daily_rate <= $req['budget_per_day']) $score += 25;
        }
        if ($req['features'] ?? false) {
            $factors++;
            $assetFeatures = $asset->features ?? [];
            $matches = count(array_intersect($req['features'], $assetFeatures));
            $score += min(20, $matches * 5);
        }
        if ($req['terrain'] ?? false) {
            $factors++;
            if ($req['terrain'] === 'offroad' && in_array($asset->asset_type, ['suv', 'truck'])) $score += 25;
        }

        return $factors > 0 ? round($score / $factors, 1) : 50;
    }

    private function getMatchReasons(UrbanGoodzRentalAsset $asset, array $req): array
    {
        $reasons = [];
        if ($req['passengers'] ?? false) {
            $reasons[] = "Seats {$asset->passenger_capacity} (need {$req['passengers']})";
        }
        if ($req['budget_per_day'] ?? false) {
            $reasons[] = '$' . number_format($asset->daily_rate, 2) . '/day (budget: $' . $req['budget_per_day'] . ')';
        }
        if ($req['features'] ?? false) {
            $matches = array_intersect($req['features'], $asset->features ?? []);
            if ($matches) $reasons[] = 'Has: ' . implode(', ', $matches);
        }
        return $reasons;
    }

    // ─── AVAILABILITY CALENDAR ────────────────────────────────────────

    public function checkAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $asset = UrbanGoodzRentalAsset::findOrFail($data['asset_id']);

        $bookings = UrbanGoodzRentalBooking::where('rental_asset_id', $data['asset_id'])
            ->where('status', 'confirmed')
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                  ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                  ->orWhere(function ($q2) use ($data) {
                      $q2->where('start_date', '<=', $data['start_date'])
                         ->where('end_date', '>=', $data['end_date']);
                  });
            })
            ->get(['start_date', 'end_date']);

        $isAvailable = $bookings->isEmpty();

        // Generate calendar view
        $calendar = [];
        $current = \Carbon\Carbon::parse($data['start_date']);
        $end = \Carbon\Carbon::parse($data['end_date']);

        while ($current <= $end) {
            $dayBooked = $bookings->contains(function ($b) use ($current) {
                return $current->between($b->start_date, $b->end_date);
            });

            $calendar[] = [
                'date' => $current->format('Y-m-d'),
                'available' => !$dayBooked,
                'booked_by' => $dayBooked ? 'confirmed' : null,
            ];
            $current->addDay();
        }

        return response()->json([
            'success' => true,
            'asset_id' => $asset->id,
            'asset_title' => $asset->title,
            'is_available' => $isAvailable,
            'calendar' => $calendar,
            'blocked_dates' => $bookings->map(fn($b) => [
                'start' => $b->start_date,
                'end' => $b->end_date,
            ])->toArray(),
        ]);
    }

    // ─── QUOTE GENERATION ──────────────────────────────────────────────

    public function generateQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'insurance' => ['nullable', 'string', 'in:basic,premium,none'],
            'additional_driver' => ['nullable', 'boolean'],
            'mileage_limit' => ['nullable', 'string', 'in:unlimited,100_per_day,200_per_day'],
        ]);

        $asset = UrbanGoodzRentalAsset::findOrFail($data['asset_id']);

        $days = \Carbon\Carbon::parse($data['start_date'])->diffInDays(\Carbon\Carbon::parse($data['end_date'])) + 1;

        $baseRate = $asset->daily_rate * $days;
        $hourlyRate = $asset->hourly_rate ? $asset->hourly_rate * min(24, $days * 24) : null;

        // Insurance
        $insuranceCost = 0;
        if ($data['insurance'] === 'basic') $insuranceCost = 15 * $days;
        elseif ($data['insurance'] === 'premium') $insuranceCost = 30 * $days;

        // Additional driver
        $additionalDriverCost = ($data['additional_driver'] ?? false) ? 10 * $days : 0;

        // Mileage
        $mileageCost = 0;
        if ($data['mileage_limit'] === '100_per_day') $mileageCost = 0.25 * 100 * $days;
        elseif ($data['mileage_limit'] === '200_per_day') $mileageCost = 0.20 * 200 * $days;

        // Weekly discount
        $discount = 0;
        if ($days >= 7) {
            $discount = $baseRate * 0.10; // 10% weekly
        } elseif ($days >= 30) {
            $discount = $baseRate * 0.20; // 20% monthly
        }

        $subtotal = $baseRate + $insuranceCost + $additionalDriverCost + $mileageCost - $discount;
        $tax = $subtotal * 0.0825; // 8.25% tax
        $total = $subtotal + $tax;

        return response()->json([
            'success' => true,
            'quote' => [
                'asset' => [
                    'id' => $asset->id,
                    'title' => $asset->title,
                    'asset_type' => $asset->asset_type,
                ],
                'period' => [
                    'start' => $data['start_date'],
                    'end' => $data['end_date'],
                    'days' => $days,
                ],
                'breakdown' => [
                    'base_rate' => '$' . number_format($baseRate, 2),
                    'insurance' => $insuranceCost ? '$' . number_format($insuranceCost, 2) : 'Not selected',
                    'additional_driver' => $additionalDriverCost ? '$' . number_format($additionalDriverCost, 2) : 'Not selected',
                    'mileage' => $mileageCost ? '$' . number_format($mileageCost, 2) : 'Unlimited included',
                    'weekly_discount' => $discount ? '-$' . number_format($discount, 2) : 'None',
                    'subtotal' => '$' . number_format($subtotal, 2),
                    'tax' => '$' . number_format($tax, 2),
                ],
                'total' => '$' . number_format($total, 2),
                'deposit' => '$' . number_format($asset->deposit_amount, 2),
                'valid_until' => now()->addHours(24)->toISOString(),
            ],
        ]);
    }

    // ─── EXTENSION ─────────────────────────────────────────────────────

    public function requestExtension(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'additional_days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $booking = UrbanGoodzRentalBooking::where('id', $data['booking_id'])
            ->where('customer_id', auth('api')->id())
            ->where('status', 'confirmed')
            ->firstOrFail();

        $asset = $booking->rentalAsset;

        // Check availability for extension period
        $newEnd = \Carbon\Carbon::parse($booking->end_date)->addDays($data['additional_days']);

        $conflict = UrbanGoodzRentalBooking::where('rental_asset_id', $booking->rental_asset_id)
            ->where('status', 'confirmed')
            ->where('id', '!=', $booking->id)
            ->where(function ($q) use ($booking, $newEnd) {
                $q->whereBetween('start_date', [$booking->end_date, $newEnd])
                  ->orWhereBetween('end_date', [$booking->end_date, $newEnd])
                  ->orWhere(function ($q2) use ($booking, $newEnd) {
                      $q2->where('start_date', '<=', $booking->end_date)
                         ->where('end_date', '>=', $newEnd);
                  });
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Asset not available for the requested extension period.',
            ], 422);
        }

        $additionalCost = $asset->daily_rate * $data['additional_days'];

        return response()->json([
            'success' => true,
            'message' => 'Extension available. Confirm to proceed.',
            'extension' => [
                'additional_days' => $data['additional_days'],
                'new_end_date' => $newEnd->format('Y-m-d'),
                'additional_cost' => '$' . number_format($additionalCost, 2),
                'current_end_date' => $booking->end_date,
            ],
        ]);
    }

    // ─── LATE RETURN ───────────────────────────────────────────────────

    public function handleLateReturn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'hours_late' => ['required', 'numeric', 'min:0.25', 'max:72'],
        ]);

        $booking = UrbanGoodzRentalBooking::where('id', $data['booking_id'])
            ->where('customer_id', auth('api')->id())
            ->firstOrFail();

        $asset = $booking->rentalAsset;
        $hourlyRate = $asset->hourly_rate ?? ($asset->daily_rate / 24);
        $lateFee = $hourlyRate * $data['hours_late'] * 1.5; // 1.5x hourly

        $gracePeriod = 1; // hour
        $billableHours = max(0, $data['hours_late'] - $gracePeriod);
        $totalLateFee = $hourlyRate * $billableHours * 1.5;

        // Check if already charged
        $existingLateFee = $booking->late_fee ?? 0;

        return response()->json([
            'success' => true,
            'late_return' => [
                'hours_late' => $data['hours_late'],
                'grace_period_hours' => $gracePeriod,
                'billable_hours' => round($billableHours, 2),
                'hourly_rate' => '$' . number_format($hourlyRate, 2),
                'late_multiplier' => '1.5x',
                'late_fee' => '$' . number_format($totalLateFee, 2),
                'previous_late_fee' => '$' . number_format($existingLateFee, 2),
                'total_late_fees' => '$' . number_format($existingLateFee + $totalLateFee, 2),
                'options' => [
                    'pay_now' => 'Charge to payment method on file',
                    'extend_rental' => 'Convert to extension at daily rate',
                    'dispute' => 'Contact support if error',
                ],
            ],
        ]);
    }

    // ─── DAMAGE REPORT ─────────────────────────────────────────────────

    public function reportDamage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['string'], // base64
            'description' => ['required', 'string', 'max:1000'],
            'damage_type' => ['required', 'string', 'in:scratch,dent,crack,broken,stain,tear,other'],
            'location_on_vehicle' => ['required', 'string'],
            'severity' => ['required', 'string', 'in:minor,moderate,severe'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $booking = UrbanGoodzRentalBooking::where('id', $data['booking_id'])
            ->where('customer_id', auth('api')->id())
            ->firstOrFail();

        // Analyze photos with AI
        $aiAnalysis = [];
        foreach ($data['photos'] as $photo) {
            $result = $this->packageScanAI->assessPackageCondition($photo, 'pickup');
            $aiAnalysis[] = $result;
        }

        $damageRecord = \DB::table('urban_goodz_rental_damages')->insertGetId([
            'rental_booking_id' => $booking->id,
            'reported_by' => auth('api')->id(),
            'photos' => json_encode($data['photos']),
            'description' => $data['description'],
            'damage_type' => $data['damage_type'],
            'location_on_vehicle' => $data['location_on_vehicle'],
            'severity' => $data['severity'],
            'occurred_at' => $data['occurred_at'] ?? now(),
            'ai_analysis' => json_encode($aiAnalysis),
            'status' => 'reported',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Estimate repair cost
        $estimatedCost = $this->estimateRepairCost($data['damage_type'], $data['severity'], $data['location_on_vehicle']);

        return response()->json([
            'success' => true,
            'damage_report_id' => $damageRecord,
            'message' => 'Damage reported. Our team will review and contact you.',
            'ai_analysis' => $aiAnalysis,
            'estimated_repair_cost' => '$' . number_format($estimatedCost, 2),
            'next_steps' => [
                'Review by operations team within 24 hours',
                'Repair estimate will be provided',
                'Deposit hold may be placed for estimated amount',
            ],
        ]);
    }

    private function estimateRepairCost(string $type, string $severity, string $location): float
    {
        $base = match($type) {
            'scratch' => 150,
            'dent' => 300,
            'crack' => 250,
            'broken' => 500,
            'stain' => 100,
            'tear' => 200,
            default => 200,
        };

        $multiplier = match($severity) {
            'minor' => 1.0,
            'moderate' => 2.0,
            'severe' => 3.5,
            default => 1.0,
        };

        $locationMultiplier = in_array($location, ['windshield', 'roof', 'engine']) ? 1.5 : 1.0;

        return $base * $multiplier * $locationMultiplier;
    }

    // ─── RETURN INSPECTION ─────────────────────────────────────────────

    public function returnInspection(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_id' => ['required', 'integer'],
            'photos' => ['required', 'array', 'min:4', 'max:15'],
            'photos.*' => ['string'],
            'mileage_start' => ['nullable', 'numeric', 'min:0'],
            'mileage_end' => ['nullable', 'numeric', 'min:0'],
            'fuel_level' => ['nullable', 'string', 'in:full,3/4,1/2,1/4,empty'],
            'condition_notes' => ['nullable', 'string'],
        ]);

        $booking = UrbanGoodzRentalBooking::where('id', $data['booking_id'])
            ->where('customer_id', auth('api')->id())
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->firstOrFail();

        // AI condition assessment
        $assessments = [];
        foreach ($data['photos'] as $photo) {
            $result = $this->packageScanAI->assessPackageCondition($photo, 'delivery');
            $assessments[] = $result;
        }

        $overallCondition = $this->determineOverallCondition($assessments);

        // Calculate charges
        $charges = [];
        if ($data['mileage_start'] !== null && $data['mileage_end'] !== null) {
            $milesDriven = $data['mileage_end'] - $data['mileage_start'];
            $asset = $booking->rentalAsset;
            $includedMiles = $asset->in = 100 * ($booking->end_date->diffInDays($booking->start_date) + 1);
            if ($milesDriven > $includedMilesin) {
                $excess = $milesDriven - $includedMilesin;
                $charges[] = [
                    'type' => 'excess_mileage',
                    'miles' => $excess,
                    'rate_per_mile' => 0.25,
                    'amount' => $excess * 0.25,
                ];
            }
        }

        if ($data['fuel_level'] !== null && $data['fuel_level'] !== 'full') {
            $charges[] = [
                'type' => 'fuel_refill',
                'level_returned' => $data['fuel_level'],
                'amount' => 50, // flat fee
            ];
        }

        $totalCharges = array_sum(array_column($charges, 'amount'));

        // Update booking
        $booking->update([
            'status' => 'completed',
            'actual_end_date' => now(),
            'end_mileage' => $data['mileage_end'] ?? $booking->end_mileage,
            'fuel_level' => $data['fuel_level'] ?? $booking->fuel_level,
            'additional_charges' => $totalCharges,
            'charges_breakdown' => json_encode($charges),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Return inspection completed.',
            'inspection' => [
                'booking_id' => $booking->id,
                'overall_condition' => $overallCondition,
                'ai_assessments' => $assessments,
                'charges' => $charges,
                'total_additional_charges' => '$' . number_format($totalCharges, 2),
                'deposit_refund' => max(0, $booking->deposit_amount - $totalCharges),
                'refund_timeline' => '5-10 business days',
            ],
        ]);
    }

    private function determineOverallCondition(array $assessments): string
    {
        $conditions = array_column($assessments, 'overall_condition');
        if (in_array('compromised', $conditions)) return 'compromised';
        if (in_array('damaged', $conditions)) return 'damaged';
        return 'good';
    }
}