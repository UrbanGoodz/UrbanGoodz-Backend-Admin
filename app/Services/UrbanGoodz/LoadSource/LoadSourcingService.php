<?php

namespace App\Services\UrbanGoodz\LoadSource;

use App\Models\DeliveryMan;
use App\Models\DriverLoadPreference;
use App\Models\ExternalLoad;
use App\Models\LoadDuplicate;
use App\Models\LoadRecommendation;
use App\Models\LoadSource;
use App\Models\LoadSourceError;
use App\Models\LoadSourceSearch;
use App\Models\LoadSourceSearchResult;
use App\Models\LoadSourceSyncRun;
use App\Models\LoadSourcingSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoadSourcingService
{
    private LoadNormalizer $normalizer;

    private array $defaultWeights = [
        'profit' => 25,
        'rate_per_mile' => 15,
        'deadhead' => 15,
        'equipment_match' => 15,
        'schedule_feasibility' => 10,
        'broker_quality' => 10,
        'return_load' => 5,
        'driver_preference' => 5,
    ];

    private array $defaultSettings = [
        'platform_fee_percent' => 12.0,
        'fuel_cost_per_mile' => 0.75,
        'toll_estimation_per_mile' => 0.05,
        'default_max_deadhead_miles' => 100,
        'minimum_confidence_threshold' => 30,
        'auto_alert_threshold' => 70,
        'scoring_weights' => null,
    ];

    public function __construct(?LoadNormalizer $normalizer = null)
    {
        $this->normalizer = $normalizer ?? new LoadNormalizer();
    }

    public function getWeights(): array
    {
        $custom = LoadSourcingSetting::get('scoring_weights');
        if (is_array($custom) && count($custom) === count($this->defaultWeights)) {
            return $custom;
        }
        return $this->defaultWeights;
    }

    public function getSetting(string $key, $default = null)
    {
        $defaults = $this->defaultSettings;
        $value = LoadSourcingSetting::get($key, $defaults[$key] ?? $default);
        return $value;
    }

    public function searchAllSources(array $criteria, ?int $searchedBy = null, ?string $searchedByType = null): array
    {
        $startTime = microtime(true);
        $allLoads = [];
        $errors = [];
        $sources = LoadSource::enabled()->get();

        foreach ($sources as $source) {
            $adapter = $this->resolveAdapter($source->source_key);
            if (!$adapter) continue;

            $result = $adapter->search($criteria);
            if ($result['success'] && !empty($result['loads'])) {
                foreach ($result['loads'] as $loadData) {
                    $normalized = $this->normalizer->normalize($loadData, $source->id);
                    $externalLoad = $this->normalizer->persistNormalized($normalized);
                    if (!$externalLoad->is_duplicate) {
                        $allLoads[] = $externalLoad;
                    }
                }
                $source->recordSync(count($result['loads']), count($result['loads']), 0, 0, 0);
            } elseif (!$result['success']) {
                $errors[] = ['source' => $source->source_key, 'error' => $result['error'] ?? 'Unknown error'];
                $source->recordError($result['error'] ?? 'Search failed');
                LoadSourceError::create([
                    'source_id' => $source->id,
                    'error_code' => 'SEARCH_FAILED',
                    'error_message' => $result['error'] ?? 'Search failed',
                    'context' => $criteria,
                ]);
            }
        }

        $durationMs = (microtime(true) - $startTime) * 1000;

        $search = LoadSourceSearch::create([
            'searched_by' => $searchedBy,
            'searched_by_type' => $searchedByType,
            'search_scope' => 'all_sources',
            'criteria' => $criteria,
            'result_count' => count($allLoads),
            'duration_ms' => round($durationMs, 2),
            'completed' => empty($errors) || count($errors) < $sources->count(),
            'error_message' => !empty($errors) ? json_encode($errors) : null,
        ]);

        return [
            'loads' => $allLoads,
            'count' => count($allLoads),
            'errors' => $errors,
            'search_id' => $search->id,
            'duration_ms' => round($durationMs, 2),
        ];
    }

    public function searchSource(string $sourceKey, array $criteria, ?int $searchedBy = null): array
    {
        $startTime = microtime(true);
        $source = LoadSource::where('source_key', $sourceKey)->first();

        if (!$source) {
            return ['success' => false, 'error' => "Source '{$sourceKey}' not found", 'loads' => []];
        }

        $adapter = $this->resolveAdapter($sourceKey);
        if (!$adapter) {
            return ['success' => false, 'error' => "No adapter for '{$sourceKey}'", 'loads' => []];
        }

        $result = $adapter->search($criteria);
        $loads = [];
        if ($result['success']) {
            foreach ($result['loads'] as $loadData) {
                $normalized = $this->normalizer->normalize($loadData, $source->id);
                $externalLoad = $this->normalizer->persistNormalized($normalized);
                if (!$externalLoad->is_duplicate) {
                    $loads[] = $externalLoad;
                }
            }
            $source->recordSync(count($result['loads']), count($loads), 0, 0, 0);
        }

        $durationMs = (microtime(true) - $startTime) * 1000;

        $search = LoadSourceSearch::create([
            'source_id' => $source->id,
            'searched_by' => $searchedBy,
            'searched_by_type' => 'admin',
            'search_scope' => 'single_source',
            'criteria' => $criteria,
            'result_count' => count($loads),
            'duration_ms' => round($durationMs, 2),
        ]);

        return [
            'success' => $result['success'],
            'loads' => $loads,
            'count' => count($loads),
            'error' => $result['error'] ?? null,
            'search_id' => $search->id,
        ];
    }

    public function generateRecommendations(int $driverId, int $limit = 20): array
    {
        $driver = DeliveryMan::find($driverId);
        if (!$driver) {
            return ['success' => false, 'error' => 'Driver not found', 'recommendations' => []];
        }

        $preferences = DriverLoadPreference::where('delivery_man_id', $driverId)->first();
        $weights = $this->getWeights();

        $availableLoads = ExternalLoad::where('status', 'available')
            ->where('is_duplicate', false)
            ->get();

        $recommendations = [];

        foreach ($availableLoads as $load) {
            if (!$this->isEligible($load, $driver, $preferences)) {
                continue;
            }

            $scoreResult = $this->scoreLoad($load, $driver, $preferences, $weights);

            if ($scoreResult['total_score'] < $this->getSetting('minimum_confidence_threshold', 30)) {
                continue;
            }

            $recommendation = LoadRecommendation::updateOrCreate(
                [
                    'external_load_id' => $load->id,
                    'delivery_man_id' => $driverId,
                ],
                [
                    'score' => $scoreResult['total_score'],
                    'confidence_level' => $scoreResult['confidence_level'],
                    'estimated_driver_net' => $scoreResult['estimated_driver_net'],
                    'net_per_total_mile' => $scoreResult['net_per_total_mile'],
                    'deadhead_miles' => $load->distance_deadhead,
                    'equipment_match' => $scoreResult['equipment_match'],
                    'certification_match' => $scoreResult['certification_match'],
                    'schedule_feasible' => $scoreResult['schedule_feasible'],
                    'broker_risk' => $scoreResult['broker_risk'],
                    'reasons_recommended' => $scoreResult['reasons_recommended'],
                    'reasons_penalized' => $scoreResult['reasons_penalized'],
                    'status' => 'pending',
                    'expires_at' => now()->addHours(48),
                ]
            );

            $recommendations[] = $recommendation;
        }

        usort($recommendations, fn($a, $b) => $b->score <=> $a->score);
        $recommendations = array_slice($recommendations, 0, $limit);

        return [
            'success' => true,
            'recommendations' => $recommendations,
            'count' => count($recommendations),
            'driver_id' => $driverId,
        ];
    }

    public function scoreLoad(ExternalLoad $load, DeliveryMan $driver, ?DriverLoadPreference $preferences, array $weights): array
    {
        $reasonsRecommended = [];
        $reasonsPenalized = [];
        $componentScores = [];

        $estimatedNet = $this->calculateEstimatedNet($load);
        $totalDistance = ($load->distance_loaded ?? 0) + ($load->distance_deadhead ?? 0);
        $netPerTotalMile = $totalDistance > 0 ? round($estimatedNet / $totalDistance, 4) : 0;

        $profitScore = $this->scoreProfit($estimatedNet, $netPerTotalMile);
        $componentScores['profit'] = $profitScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $profitScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $profitScore['penalties']);

        $rateScore = $this->scoreRatePerMile($load, $preferences);
        $componentScores['rate_per_mile'] = $rateScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $rateScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $rateScore['penalties']);

        $deadheadScore = $this->scoreDeadhead($load, $preferences);
        $componentScores['deadhead'] = $deadheadScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $deadheadScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $deadheadScore['penalties']);

        $equipmentScore = $this->scoreEquipmentMatch($load, $driver);
        $componentScores['equipment_match'] = $equipmentScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $equipmentScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $equipmentScore['penalties']);

        $scheduleScore = $this->scoreScheduleFeasibility($load, $driver);
        $componentScores['schedule_feasibility'] = $scheduleScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $scheduleScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $scheduleScore['penalties']);

        $brokerScore = $this->scoreBrokerQuality($load);
        $componentScores['broker_quality'] = $brokerScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $brokerScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $brokerScore['penalties']);

        $returnScore = $this->scoreReturnLoadPotential($load, $driver);
        $componentScores['return_load'] = $returnScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $returnScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $returnScore['penalties']);

        $preferenceScore = $this->scoreDriverPreference($load, $preferences);
        $componentScores['driver_preference'] = $preferenceScore['score'];
        $reasonsRecommended = array_merge($reasonsRecommended, $preferenceScore['reasons']);
        $reasonsPenalized = array_merge($reasonsPenalized, $preferenceScore['penalties']);

        $totalScore = 0;
        $totalWeight = 0;
        foreach ($componentScores as $key => $val) {
            $w = $weights[$key] ?? 0;
            $totalScore += $val * $w;
            $totalWeight += $w;
        }
        $totalScore = $totalWeight > 0 ? round($totalScore / $totalWeight) : 0;

        $confidenceLevel = match(true) {
            $totalScore >= 75 => 'high',
            $totalScore >= 50 => 'medium',
            default => 'low',
        };

        $missingData = empty($load->gross_rate) || is_null($load->distance_loaded) || is_null($load->distance_deadhead);
        if ($missingData) {
            $confidenceLevel = 'low';
            $reasonsPenalized[] = 'Missing key data reduces confidence';
        }

        return [
            'total_score' => min(100, max(0, $totalScore)),
            'confidence_level' => $confidenceLevel,
            'estimated_driver_net' => $estimatedNet,
            'net_per_total_mile' => $netPerTotalMile,
            'equipment_match' => $equipmentScore['matched'],
            'certification_match' => $scheduleScore['cert_match'],
            'schedule_feasible' => $scheduleScore['feasible'],
            'broker_risk' => $brokerScore['risk_level'],
            'reasons_recommended' => array_values(array_unique($reasonsRecommended)),
            'reasons_penalized' => array_values(array_unique($reasonsPenalized)),
            'component_scores' => $componentScores,
        ];
    }

    public function isEligible(ExternalLoad $load, DeliveryMan $driver, ?DriverLoadPreference $preferences = null): bool
    {
        $certs = $load->certifications_required ?? [];
        if (!empty($certs)) {
            if (in_array('cdl', $certs) && empty($driver->cdl_status)) return false;
            if (in_array('hazmat', $certs) && !$driver->has_hazmat) return false;
            if (in_array('medical_courier', $certs) && !$driver->has_medical_courier_training) return false;
        }

        $equip = $this->normalizeEquipmentType($load->equipment_type);
        if ($equip) {
            $driverEquip = $this->normalizeEquipmentType($driver->vehicle_type);
            if ($driverEquip && $equip !== $driverEquip) return false;
        }

        if ($load->weight && $driver->max_weight_lbs && $load->weight > $driver->max_weight_lbs) {
            return false;
        }

        if ($preferences) {
            if ($preferences->max_deadhead_miles && $load->distance_deadhead && $load->distance_deadhead > $preferences->max_deadhead_miles) {
                return false;
            }
            if (!empty($preferences->excluded_origins) && $load->origin_state) {
                if (in_array($load->origin_state, $preferences->excluded_origins)) return false;
            }
            if (!empty($preferences->excluded_destinations) && $load->destination_state) {
                if (in_array($load->destination_state, $preferences->excluded_destinations)) return false;
            }
        }

        if (!$driver->load_board_eligible) return false;

        return true;
    }

    public function recordExternalHandoff(int $externalLoadId, int $sourceId, int $referredBy, string $referredByType, string $action, ?string $externalUrl = null): array
    {
        $load = ExternalLoad::find($externalLoadId);
        if (!$load) {
            return ['success' => false, 'error' => 'Load not found'];
        }

        $referral = \App\Models\LoadPartnerReferral::create([
            'external_load_id' => $externalLoadId,
            'source_id' => $sourceId,
            'referred_by' => $referredBy,
            'referred_by_type' => $referredByType,
            'referral_action' => $action,
            'external_url' => $externalUrl,
        ]);

        return ['success' => true, 'referral' => $referral];
    }

    public function recordBookingConfirmation(int $referralId, bool $booked, ?string $notes = null): array
    {
        $referral = \App\Models\LoadPartnerReferral::find($referralId);
        if (!$referral) {
            return ['success' => false, 'error' => 'Referral not found'];
        }

        $referral->update([
            'user_confirmed_booked' => $booked,
            'booking_status' => $booked ? 'booked' : 'not_booked',
            'notes' => $notes,
        ]);

        if ($booked) {
            $referral->externalLoad()->update(['status' => 'booked']);
        }

        return ['success' => true, 'referral' => $referral];
    }

    private function calculateEstimatedNet(ExternalLoad $load): float
    {
        $gross = (float) ($load->gross_rate ?? 0);
        $fuelCost = $this->estimateFuelCost($load);
        $tolls = $this->estimateTolls($load);
        $platformFee = $gross * ($this->getSetting('platform_fee_percent', 12.0) / 100);
        $net = $gross - $fuelCost - $tolls - $platformFee;
        return round(max(0, $net), 2);
    }

    private function estimateFuelCost(ExternalLoad $load): float
    {
        $totalMiles = ($load->distance_loaded ?? 0) + ($load->distance_deadhead ?? 0);
        $fuelRate = $this->getSetting('fuel_cost_per_mile', 0.75);
        return round($totalMiles * $fuelRate, 2);
    }

    private function estimateTolls(ExternalLoad $load): float
    {
        $totalMiles = ($load->distance_loaded ?? 0) + ($load->distance_deadhead ?? 0);
        $tollRate = $this->getSetting('toll_estimation_per_mile', 0.05);
        return round($totalMiles * $tollRate, 2);
    }

    private function scoreProfit(float $estimatedNet, float $netPerTotalMile): array
    {
        $reasons = [];
        $penalties = [];

        if ($estimatedNet <= 0) {
            return ['score' => 0, 'reasons' => $reasons, 'penalties' => ['Negative or zero estimated net profit']];
        }

        if ($estimatedNet >= 500) {
            $reasons[] = 'High-value load ($' . number_format($estimatedNet, 0) . ' estimated net)';
            $score = 100;
        } elseif ($estimatedNet >= 300) {
            $reasons[] = 'Good profit ($' . number_format($estimatedNet, 0) . ' estimated net)';
            $score = 80;
        } elseif ($estimatedNet >= 150) {
            $score = 60;
        } elseif ($estimatedNet >= 50) {
            $score = 40;
            $penalties[] = 'Low estimated net profit';
        } else {
            $score = 20;
            $penalties[] = 'Very low estimated net profit';
        }

        return ['score' => $score, 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function scoreRatePerMile(ExternalLoad $load, ?DriverLoadPreference $preferences): array
    {
        $reasons = [];
        $penalties = [];
        $rpm = (float) ($load->rate_per_loaded_mile ?? 0);

        if ($rpm <= 0) {
            $distance = $load->distance_loaded ?? 0;
            $gross = (float) ($load->gross_rate ?? 0);
            $rpm = $distance > 0 ? $gross / $distance : 0;
        }

        if ($preferences && $preferences->min_rate_per_mile && $rpm < $preferences->min_rate_per_mile) {
            return ['score' => 0, 'reasons' => $reasons, 'penalties' => ["Rate $".number_format($rpm, 2)."/mile below driver minimum"]];
        }

        if ($rpm >= 3.0) {
            $reasons[] = "Excellent rate: $".number_format($rpm, 2)."/mile";
            $score = 100;
        } elseif ($rpm >= 2.5) {
            $reasons[] = "Good rate: $".number_format($rpm, 2)."/mile";
            $score = 85;
        } elseif ($rpm >= 2.0) {
            $score = 70;
        } elseif ($rpm >= 1.5) {
            $score = 50;
            $penalties[] = "Below average rate per mile";
        } else {
            $score = 20;
            $penalties[] = "Low rate per mile";
        }

        return ['score' => $score, 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function scoreDeadhead(ExternalLoad $load, ?DriverLoadPreference $preferences): array
    {
        $reasons = [];
        $penalties = [];
        $deadhead = (float) ($load->distance_deadhead ?? 0);

        if ($deadhead <= 10) {
            $reasons[] = 'Minimal deadhead (' . number_format($deadhead, 0) . ' mi)';
            $score = 100;
        } elseif ($deadhead <= 30) {
            $reasons[] = 'Low deadhead (' . number_format($deadhead, 0) . ' mi)';
            $score = 85;
        } elseif ($deadhead <= 75) {
            $score = 60;
        } elseif ($deadhead <= 150) {
            $score = 35;
            $penalties[] = 'High deadhead distance';
        } else {
            $score = 10;
            $penalties[] = 'Excessive deadhead (' . number_format($deadhead, 0) . ' mi)';
        }

        return ['score' => $score, 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function scoreEquipmentMatch(ExternalLoad $load, DeliveryMan $driver): array
    {
        $reasons = [];
        $penalties = [];
        $loadEquip = $this->normalizeEquipmentType($load->equipment_type);
        $driverEquip = $this->normalizeEquipmentType($driver->vehicle_type);

        if (!$loadEquip) {
            return ['score' => 70, 'matched' => true, 'reasons' => ['No equipment requirement specified'], 'penalties' => []];
        }

        if (!$driverEquip) {
            return ['score' => 30, 'matched' => false, 'reasons' => [], 'penalties' => ['Driver vehicle type unknown']];
        }

        if ($loadEquip === $driverEquip) {
            $reasons[] = 'Equipment matches driver vehicle (' . $loadEquip . ')';
            return ['score' => 100, 'matched' => true, 'reasons' => $reasons, 'penalties' => []];
        }

        $compatible = $this->isEquipmentCompatible($loadEquip, $driverEquip);
        if ($compatible) {
            $reasons[] = 'Equipment type compatible';
            return ['score' => 75, 'matched' => true, 'reasons' => $reasons, 'penalties' => []];
        }

        return ['score' => 0, 'matched' => false, 'reasons' => [], 'penalties' => ['Equipment mismatch: load needs ' . $loadEquip . ', driver has ' . $driverEquip]];
    }

    private function scoreScheduleFeasibility(ExternalLoad $load, DeliveryMan $driver): array
    {
        $reasons = [];
        $penalties = [];
        $certMatch = true;

        if ($load->pickup_end && $load->pickup_end->isPast()) {
            return ['score' => 0, 'cert_match' => $certMatch, 'feasible' => false, 'reasons' => [], 'penalties' => ['Pickup window has expired']];
        }

        $certs = $load->certifications_required ?? [];
        if (in_array('cdl', $certs) && empty($driver->cdl_status)) {
            $certMatch = false;
            $penalties[] = 'CDL required but driver lacks CDL';
        }
        if (in_array('hazmat', $certs) && !$driver->has_hazmat) {
            $certMatch = false;
            $penalties[] = 'Hazmat endorsement required';
        }

        if (!$certMatch) {
            return ['score' => 0, 'cert_match' => false, 'feasible' => false, 'reasons' => [], 'penalties' => $penalties];
        }

        if ($load->pickup_start) {
            $hoursUntilPickup = (now()->diffInHours($load->pickup_start, false));
            if ($hoursUntilPickup < 0) {
                $penalties[] = 'Pickup window already started';
            } elseif ($hoursUntilPickup > 4 && $hoursUntilPickup < 72) {
                $reasons[] = 'Pickup timing is feasible';
            }
        }

        $score = empty($penalties) ? 90 : max(20, 90 - (count($penalties) * 30));
        $feasible = $score >= 50;

        return ['score' => $score, 'cert_match' => $certMatch, 'feasible' => $feasible, 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function scoreBrokerQuality(ExternalLoad $load): array
    {
        $reasons = [];
        $penalties = [];
        $riskLevel = 'unknown';

        if ($load->broker_credit_status && $load->broker_credit_status !== 'unknown') {
            $riskLevel = match($load->broker_credit_status) {
                'excellent' => 'low',
                'good' => 'low',
                'fair' => 'medium',
                'poor' => 'high',
                default => 'unknown',
            };
        }

        if ($load->broker_rating && $load->broker_rating >= 4.0) {
            $reasons[] = "Strong broker rating (" . $load->broker_rating . "/5)";
            $score = 95;
        } elseif ($load->broker_rating && $load->broker_rating >= 3.0) {
            $score = 70;
        } elseif ($riskLevel === 'high') {
            $score = 20;
            $penalties[] = 'Broker credit risk: ' . $load->broker_credit_status;
        } else {
            $score = 50;
            $penalties[] = 'Broker rating unavailable';
        }

        return ['score' => $score, 'risk_level' => $riskLevel, 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function scoreReturnLoadPotential(ExternalLoad $load, DeliveryMan $driver): array
    {
        $reasons = [];
        $penalties = [];
        $score = 50;

        if ($load->destination_state) {
            $returnSearch = ExternalLoad::where('status', 'available')
                ->where('origin_state', $load->destination_state)
                ->where('is_duplicate', false)
                ->count();

            if ($returnSearch >= 5) {
                $reasons[] = 'High return load availability at destination (' . $returnSearch . ' loads)';
                $score = 95;
            } elseif ($returnSearch >= 2) {
                $reasons[] = 'Some return loads at destination';
                $score = 75;
            } elseif ($returnSearch === 0) {
                $penalties[] = 'No return loads found at destination';
                $score = 20;
            }
        }

        return ['score' => $score, 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function scoreDriverPreference(ExternalLoad $load, ?DriverLoadPreference $preferences): array
    {
        $reasons = [];
        $penalties = [];
        $score = 50;

        if (!$preferences) {
            return ['score' => $score, 'reasons' => ['No driver preferences set'], 'penalties' => []];
        }

        if (!empty($preferences->preferred_origins) && $load->origin_state) {
            if (in_array($load->origin_state, $preferences->preferred_origins)) {
                $reasons[] = 'Origin matches preferred lanes';
                $score += 25;
            }
        }
        if (!empty($preferences->preferred_destinations) && $load->destination_state) {
            if (in_array($load->destination_state, $preferences->preferred_destinations)) {
                $reasons[] = 'Destination matches preferred lanes';
                $score += 25;
            }
        }

        if ($preferences->prefer_short_haul && $load->distance_loaded && $load->distance_loaded <= 250) {
            $reasons[] = 'Short haul as preferred';
            $score += 10;
        }
        if ($preferences->prefer_long_haul && $load->distance_loaded && $load->distance_loaded > 500) {
            $reasons[] = 'Long haul as preferred';
            $score += 10;
        }
        if ($preferences->prefer_high_value && $load->gross_rate && $load->gross_rate >= 1000) {
            $reasons[] = 'High value as preferred';
            $score += 10;
        }

        if (!$preferences->open_to_hazmat && str_contains(strtolower($load->commodity ?? ''), 'hazmat')) {
            $penalties[] = 'Hazmat commodity but driver prefers no hazmat';
            $score -= 20;
        }

        return ['score' => min(100, max(0, $score)), 'reasons' => $reasons, 'penalties' => $penalties];
    }

    private function normalizeEquipmentType(?string $type): ?string
    {
        if (!$type) return null;
        $t = strtolower(trim($type));
        return match(true) {
            str_contains($t, 'van') || str_contains($t, 'enclosed') || str_contains($t, 'box') => 'van',
            str_contains($t, 'reefer') || str_contains($t, 'refriger') => 'reefer',
            str_contains($t, 'flat') => 'flatbed',
            str_contains($t, 'tank') => 'tanker',
            str_contains($t, 'step') => 'step_deck',
            str_contains($t, 'car') || str_contains($t, 'auto') => 'car_hauler',
            default => $t,
        };
    }

    private function isEquipmentCompatible(string $loadEquip, string $driverEquip): bool
    {
        $compatible = [
            'van' => ['van', 'dry_van', 'enclosed'],
            'dry_van' => ['van', 'dry_van', 'enclosed'],
            'flatbed' => ['flatbed', 'step_deck', 'lowboy'],
            'step_deck' => ['flatbed', 'step_deck'],
            'reefer' => ['reefer', 'van'],
        ];
        return in_array($driverEquip, $compatible[$loadEquip] ?? []);
    }

    private function resolveAdapter(string $sourceKey): ?\App\Contracts\LoadSource\LoadSourceAdapter
    {
        $adapters = [
            'urban_goodz_internal' => UrbanGoodzInternalLoadSourceAdapter::class,
            'email_inbox' => EmailLoadSourceAdapter::class,
            'manual_import' => ManualLoadSourceAdapter::class,
            'dat' => DatLoadSourceAdapter::class,
            'truckstop' => TruckstopLoadSourceAdapter::class,
            'trulos' => TrulosLoadSourceAdapter::class,
            'tb_load' => TbLoadLoadSourceAdapter::class,
            'direct_freight' => DirectFreightLoadSourceAdapter::class,
            'trucker_path' => TruckerPathLoadSourceAdapter::class,
            'trucksmarter' => TruckSmarterLoadSourceAdapter::class,
        ];

        $class = $adapters[$sourceKey] ?? null;
        if (!$class) return null;

        $config = config('urban_goodz_load_board.providers.' . $sourceKey, []);

        return new $class($config);
    }
}
