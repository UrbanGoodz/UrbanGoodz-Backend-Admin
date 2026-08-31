<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzBusinessClientJob;
use App\Models\UrbanGoodzManifest;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\DeliveryMan;
use App\Services\UrbanGoodz\UrbanGoodzRouteClusteringService;
use App\Services\UrbanGoodz\LoadSource\LoadSourcingService;
use Illuminate\Support\Facades\Log;

class BusinessClientAIService
{
    private UrbanGoodzAIService $ai;
    private LoadSourcingService $loadSourcing;

    public function __construct(UrbanGoodzAIService $ai, ?LoadSourcingService $loadSourcing = null)
    {
        $this->ai = $ai;
        $this->loadSourcing = $loadSourcing ?? app(LoadSourcingService::class);
    }

    /**
     * Business Portal's load-sourcing search, delegating to the canonical
     * multi-adapter LoadSourcingService rather than duplicating its logic.
     */
    public function searchExternalLoads(array $criteria, array $context = []): array
    {
        return $this->loadSourcing->searchAllSources(
            $criteria,
            $context['client_id'] ?? null,
            'business_client'
        );
    }

    public function optimizeManifest(array $jobs, array $drivers = []): array
    {
        $systemPrompt = "You are a logistics optimization engine for Urban Goodz, a delivery and logistics platform.
Analyze the delivery manifest and optimize it for maximum efficiency.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"assignments\": [
    {
      \"job_id\": number,
      \"assigned_driver_id\": number|null,
      \"driver_name\": string,
      \"reason\": string,
      \"confidence\": number
    }
  ],
  \"optimal_route_sequence\": [
    {
      \"job_id\": number,
      \"stop_order\": number,
      \"estimated_arrival\": string,
      \"estimated_duration_minutes\": number
    }
  ],
  \"time_savings\": {
    \"estimated_total_minutes_saved\": number,
    \"efficiency_gain_percent\": number,
    \"details\": string
  },
  \"potential_delays\": [
    {
      \"job_id\": number,
      \"risk_level\": \"low\"|\"medium\"|\"high\",
      \"reason\": string,
      \"mitigation\": string
    }
  ],
  \"summary\": string
}";

        $context = [
            'jobs' => $jobs,
            'available_drivers' => $drivers,
            'optimization_criteria' => [
                'minimize_total_drive_time',
                'respect_delivery_windows',
                'balance_driver_workload',
                'prioritize_urgent_packages',
                'group_geographic_areas',
            ],
        ];

        $result = $this->ai->chat($systemPrompt, "Optimize this delivery manifest. Assign drivers to jobs, determine optimal route sequence, and identify potential delays.", $context);

        return $this->parseJsonResponse($result, [
            'assignments' => [],
            'optimal_route_sequence' => [],
            'time_savings' => ['estimated_total_minutes_saved' => 0, 'efficiency_gain_percent' => 0, 'details' => ''],
            'potential_delays' => [],
            'summary' => 'Unable to optimize manifest.',
        ]);
    }

    public function generateRoutePackage(array $clientData, string $frequency = 'weekly'): array
    {
        $systemPrompt = "You are a route planning specialist for Urban Goodz, a delivery and logistics platform.
Generate a comprehensive route package plan for a business client.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"recommended_routes\": [
    {
      \"route_name\": string,
      \"route_type\": \"urban\"|\"suburban\"|\"mixed\"|\"long_haul\",
      \"estimated_stops\": number,
      \"estimated_distance_miles\": number,
      \"estimated_duration_hours\": number,
      \"time_windows\": [
        {
          \"label\": string,
          \"start\": string,
          \"end\": string,
          \"notes\": string
        }
      ],
      \"special_requirements\": [string],
      \"vehicle_recommendation\": string
    }
  ],
  \"pricing_tier_suggestion\": {
    \"tier\": \"basic\"|\"standard\"|\"premium\"|\"enterprise\",
    \"reasoning\": string,
    \"estimated_monthly_cost\": number,
    \"per_stop_rate\": number
  },
  \"schedule_summary\": {
    \"frequency\": string,
    \"optimal_days\": [string],
    \"pickup_windows\": [string],
    \"delivery_windows\": [string]
  },
  \"operational_notes\": [string],
  \"risk_factors\": [string],
  \"recommendations\": [string]
}";

        $context = [
            'client' => $clientData,
            'frequency' => $frequency,
            'available_service_types' => UrbanGoodzManifest::SERVICE_TYPES,
        ];

        $result = $this->ai->chat($systemPrompt, "Generate a route package plan for this business client with frequency: {$frequency}.", $context);

        return $this->parseJsonResponse($result, [
            'recommended_routes' => [],
            'pricing_tier_suggestion' => ['tier' => 'standard', 'reasoning' => 'Default tier', 'estimated_monthly_cost' => 0, 'per_stop_rate' => 0],
            'schedule_summary' => ['frequency' => $frequency, 'optimal_days' => [], 'pickup_windows' => [], 'delivery_windows' => []],
            'operational_notes' => [],
            'risk_factors' => [],
            'recommendations' => [],
        ]);
    }

    public function analyzeClientPerformance(int $clientId): array
    {
        $client = UrbanGoodzBusinessClient::withCount(['jobs', 'locations', 'users'])->findOrFail($clientId);

        $jobs = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->selectRaw("
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = 'delivered' OR status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled_jobs,
                SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed_jobs,
                SUM(CASE WHEN status IN ('invoiced', 'paid') THEN 1 ELSE 0 END) as invoiced_jobs,
                SUM(COALESCE(final_amount, quoted_amount, rate_offered, 0)) as total_revenue,
                SUM(CASE WHEN status = 'paid' THEN COALESCE(final_amount, quoted_amount, rate_offered, 0) ELSE 0 END) as collected_revenue,
                AVG(TIMESTAMPDIFF(HOUR, assigned_at, delivered_at)) as avg_delivery_hours,
                MIN(created_at) as first_job_date,
                MAX(created_at) as last_job_date
            ")
            ->first();

        $jobTypeBreakdown = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->selectRaw('job_type, COUNT(*) as count, SUM(COALESCE(final_amount, quoted_amount, rate_offered, 0)) as revenue')
            ->groupBy('job_type')
            ->get();

        $recentTrend = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->where('created_at', '>=', now()->subMonths(3))
            ->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as jobs,
                SUM(COALESCE(final_amount, quoted_amount, rate_offered, 0)) as revenue,
                SUM(CASE WHEN status = 'delivered' OR status = 'completed' THEN 1 ELSE 0 END) as completed
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $systemPrompt = "You are a business performance analyst for Urban Goodz, a delivery and logistics platform.
Analyze this business client's performance data and provide actionable insights.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"performance_score\": number,
  \"health_status\": \"excellent\"|\"good\"|\"at_risk\"|\"critical\",
  \"key_metrics\": {
    \"delivery_success_rate\": number,
    \"avg_delivery_time_hours\": number,
    \"revenue_collected\": number,
    \"outstanding_balance\": number,
    \"cancellation_rate\": number,
    \"delay_rate\": number
  },
  \"strengths\": [string],
  \"weaknesses\": [string],
  \"insights\": [
    {
      \"category\": string,
      \"finding\": string,
      \"impact\": \"high\"|\"medium\"|\"low\",
      \"recommendation\": string
    }
  ],
  \"strategic_recommendations\": [string],
  \"growth_opportunities\": [string],
  \"risk_flags\": [string],
  \"executive_summary\": string
}";

        $context = [
            'client' => $client->only(['company_name', 'account_type', 'status', 'billing_terms', 'credit_limit', 'territory_states', 'territory_corridors']),
            'job_stats' => $jobs,
            'job_type_breakdown' => $jobTypeBreakdown,
            'recent_trend' => $recentTrend,
        ];

        $result = $this->ai->chat($systemPrompt, "Analyze the performance of this business client and provide strategic insights.", $context);

        return $this->parseJsonResponse($result, [
            'performance_score' => 0,
            'health_status' => 'unknown',
            'key_metrics' => [],
            'strengths' => [],
            'weaknesses' => [],
            'insights' => [],
            'strategic_recommendations' => [],
            'growth_opportunities' => [],
            'risk_flags' => [],
            'executive_summary' => 'Unable to analyze client performance.',
        ]);
    }

    public function suggestPricingTier(array $clientData, array $deliveryHistory): array
    {
        $systemPrompt = "You are a pricing strategist for Urban Goodz, a delivery and logistics platform.
Suggest the optimal pricing tier for a business client based on their profile and delivery history.

Available tiers: basic, standard, premium, enterprise.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"suggested_tier\": \"basic\"|\"standard\"|\"premium\"|\"enterprise\",
  \"confidence\": number,
  \"pricing_details\": {
    \"base_monthly_fee\": number,
    \"per_stop_rate\": number,
    \"per_mile_rate\": number,
    \"fuel_surcharge_percent\": number,
    \"special_handling_surcharge\": number,
    \"volume_discount_percent\": number,
    \"estimated_monthly_total\": number
  },
  \"tier_reasoning\": string,
  \"volume_analysis\": {
    \"avg_monthly_stops\": number,
    \"avg_stop_distance_miles\": number,
    \"peak_day_concentration\": number,
    \"special_requirements_percent\": number
  },
  \"competitor_comparison\": {
    \"our_position\": \"below_market\"|\"at_market\"|\"above_market\",
    \"estimated_savings_vs_competitors\": number,
    \"differentiation_points\": [string]
  },
  \"upsell_opportunities\": [string],
  \"retention_risks\": [string],
  \"contract_recommendations\": [string]
}";

        $context = [
            'client' => $clientData,
            'delivery_history' => $deliveryHistory,
            'pricing_tiers' => [
                'basic' => 'Minimal volume, occasional deliveries',
                'standard' => 'Regular weekly deliveries, moderate volume',
                'premium' => 'High volume, priority service, dedicated routing',
                'enterprise' => 'Custom solutions, SLA guarantees, dedicated fleet',
            ],
        ];

        $result = $this->ai->chat($systemPrompt, "Suggest the optimal pricing tier for this business client based on their delivery profile and history.", $context);

        return $this->parseJsonResponse($result, [
            'suggested_tier' => 'standard',
            'confidence' => 0,
            'pricing_details' => [],
            'tier_reasoning' => 'Unable to determine optimal tier.',
            'volume_analysis' => [],
            'competitor_comparison' => [],
            'upsell_opportunities' => [],
            'retention_risks' => [],
            'contract_recommendations' => [],
        ]);
    }

    public function generateClientReport(int $clientId, string $period = 'monthly'): array
    {
        $client = UrbanGoodzBusinessClient::withCount(['jobs', 'locations', 'users'])->findOrFail($clientId);

        $dateFrom = match ($period) {
            'daily' => now()->subDay(),
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subQuarter(),
            'yearly' => now()->subYear(),
            default => now()->subMonth(),
        };

        $periodLabel = match ($period) {
            'daily' => $dateFrom->format('M d, Y'),
            'weekly' => "Week of {$dateFrom->format('M d')} - " . now()->format('M d, Y'),
            'monthly' => $dateFrom->format('F Y'),
            'quarterly' => "Q" . ceil(now()->month / 3) . " " . now()->year,
            'yearly' => (string) now()->year,
            default => $dateFrom->format('F Y'),
        };

        $jobs = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw("
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status IN ('delivered', 'completed') THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as canceled,
                SUM(CASE WHEN status = 'delayed' THEN 1 ELSE 0 END) as delayed,
                SUM(CASE WHEN status IN ('invoiced', 'paid') THEN 1 ELSE 0 END) as invoiced,
                SUM(COALESCE(final_amount, quoted_amount, rate_offered, 0)) as total_revenue,
                SUM(CASE WHEN status = 'paid' THEN COALESCE(final_amount, quoted_amount, rate_offered, 0) ELSE 0 END) as collected,
                AVG(TIMESTAMPDIFF(MINUTE, assigned_at, delivered_at)) as avg_delivery_minutes
            ")
            ->first();

        $jobTypes = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw('job_type, COUNT(*) as count, SUM(COALESCE(final_amount, quoted_amount, rate_offered, 0)) as revenue')
            ->groupBy('job_type')
            ->get();

        $dailyActivity = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->where('created_at', '>=', $dateFrom)
            ->selectRaw("
                DATE(created_at) as day,
                COUNT(*) as jobs,
                SUM(COALESCE(final_amount, quoted_amount, rate_offered, 0)) as revenue
            ")
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $systemPrompt = "You are a report analyst for Urban Goodz, a delivery and logistics platform.
Generate a comprehensive business client performance report.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"report_title\": string,
  \"period\": string,
  \"executive_summary\": string,
  \"kpis\": {
    \"total_jobs\": number,
    \"completion_rate\": number,
    \"cancellation_rate\": number,
    \"delay_rate\": number,
    \"avg_delivery_time_minutes\": number,
    \"total_revenue\": number,
    \"collected_revenue\": number,
    \"outstanding_balance\": number,
    \"collection_rate\": number,
    \"revenue_per_job\": number,
    \"client_satisfaction_estimate\": number
  },
  \"trends\": [
    {
      \"metric\": string,
      \"direction\": \"up\"|\"down\"|\"stable\",
      \"change_percent\": number,
      \"analysis\": string
    }
  ],
  \"job_type_analysis\": [
    {
      \"type\": string,
      \"count\": number,
      \"revenue\": number,
      \"percent_of_total\": number,
      \"notes\": string
    }
  ],
  \"operational_highlights\": [string],
  \"concerns\": [string],
  \"strategic_recommendations\": [
    {
      \"priority\": \"high\"|\"medium\"|\"low\",
      \"recommendation\": string,
      \"expected_impact\": string,
      \"timeline\": string
    }
  ],
  \"next_period_forecast\": {
    \"expected_jobs\": number,
    \"expected_revenue\": number,
    \"key_focus_areas\": [string]
  }
}";

        $context = [
            'client' => $client->only(['company_name', 'account_type', 'status', 'billing_terms', 'credit_limit', 'territory_states']),
            'period' => $period,
            'period_label' => $periodLabel,
            'job_stats' => $jobs,
            'job_type_breakdown' => $jobTypes,
            'daily_activity' => $dailyActivity,
        ];

        $result = $this->ai->chat($systemPrompt, "Generate a comprehensive {$period} performance report for this business client.", $context);

        return $this->parseJsonResponse($result, [
            'report_title' => "Client Report - {$periodLabel}",
            'period' => $periodLabel,
            'executive_summary' => 'Unable to generate report.',
            'kpis' => [],
            'trends' => [],
            'job_type_analysis' => [],
            'operational_highlights' => [],
            'concerns' => [],
            'strategic_recommendations' => [],
            'next_period_forecast' => [],
        ]);
    }

    public function autoScheduleJobs(int $clientId, array $jobRequests): array
    {
        $client = UrbanGoodzBusinessClient::findOrFail($clientId);

        $availableDrivers = DeliveryMan::where('application_status', 'approved')
            ->where('active', 1)
            ->get()
            ->map(fn($d) => [
                'id' => $d->id,
                'name' => $d->first_name . ' ' . $d->last_name,
                'phone' => $d->phone,
            ])
            ->toArray();

        $existingJobCount = UrbanGoodzBusinessClientJob::where('business_client_id', $clientId)
            ->whereIn('status', ['assigned', 'driver_en_route', 'picked_up', 'in_transit'])
            ->count();

        $systemPrompt = "You are an automated scheduling engine for Urban Goodz, a delivery and logistics platform.
Schedule multiple job requests for a business client, optimizing for driver availability, time windows, and route efficiency.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"scheduled_jobs\": [
    {
      \"original_request_index\": number,
      \"job_number\": string,
      \"status\": \"scheduled\"|\"conflict\"|\"deferred\",
      \"assigned_driver_id\": number|null,
      \"driver_name\": string|null,
      \"scheduled_pickup\": string,
      \"scheduled_delivery\": string,
      \"estimated_duration_minutes\": number,
      \"route_group\": number,
      \"notes\": string,
      \"conflict_reason\": string|null
    }
  ],
  \"schedule_summary\": {
    \"total_scheduled\": number,
    \"total_conflicts\": number,
    \"total_deferred\": number,
    \"drivers_utilized\": number,
    \"estimated_total_hours\": number,
    \"routes_created\": number,
    \"earliest_pickup\": string,
    \"latest_delivery\": string
  },
  \"route_groups\": [
    {
      \"group_id\": number,
      \"driver_id\": number,
      \"driver_name\": string,
      \"job_count\": number,
      \"estimated_duration_hours\": number,
      \"route_efficiency_score\": number,
      \"stops\": [string]
    }
  ],
  \"optimization_notes\": [string],
  \"warnings\": [string]
}";

        $context = [
            'client' => $client->only(['company_name', 'account_type', 'territory_states', 'territory_corridors']),
            'job_requests' => $jobRequests,
            'available_drivers' => $availableDrivers,
            'active_jobs_count' => $existingJobCount,
            'service_types' => UrbanGoodzManifest::SERVICE_TYPES,
            'scheduling_constraints' => [
                'max_driving_hours' => 10,
                'required_break_after_hours' => 5,
                'respect_driver_preferences' => true,
                'balance_workload' => true,
            ],
        ];

        $result = $this->ai->chat($systemPrompt, "Auto-schedule these {$clientId} job requests. Optimize for driver availability, time windows, and route efficiency.", $context);

        return $this->parseJsonResponse($result, [
            'scheduled_jobs' => [],
            'schedule_summary' => [
                'total_scheduled' => 0,
                'total_conflicts' => count($jobRequests),
                'total_deferred' => 0,
                'drivers_utilized' => 0,
                'estimated_total_hours' => 0,
                'routes_created' => 0,
                'earliest_pickup' => '',
                'latest_delivery' => '',
            ],
            'route_groups' => [],
            'optimization_notes' => [],
            'warnings' => ['Unable to auto-schedule jobs.'],
        ]);
    }

    public function groupPackagesForRoutes(array $packages, array $params = []): array
    {
        $packageModels = collect($packages)->map(function ($p) {
            if ($p instanceof UrbanGoodzRoutePackage) {
                return $p;
            }
            return UrbanGoodzRoutePackage::find($p['id'] ?? $p['package_id'] ?? null);
        })->filter()->values();

        if ($packageModels->isEmpty()) {
            return [
                'groups' => [],
                'total_packages' => count($packages),
                'routed_packages' => 0,
                'unrouteable_count' => count($packages),
            ];
        }

        $clusteringService = app(UrbanGoodzRouteClusteringService::class);

        $clusteringParams = [
            'maximum_packages_per_route' => $params['max_stops_per_route'] ?? 25,
            'maximum_route_miles' => $params['max_route_distance'] ?? 100,
            'preferred_cluster_radius_miles' => $params['preferred_cluster_radius_miles'] ?? 25,
            'maximum_route_duration_minutes' => $params['maximum_route_duration_minutes'] ?? 480,
            'vehicle_type' => $params['vehicle_types'][0] ?? 'cargo_van',
        ];

        $result = $clusteringService->clusterPackages($packageModels, $clusteringParams);

        $groups = array_map(function ($cluster) {
            return [
                'group_id' => $cluster['cluster_index'],
                'package_count' => $cluster['stats']['package_count'],
                'estimated_miles' => $cluster['stats']['estimated_miles'],
                'estimated_duration_minutes' => $cluster['stats']['estimated_duration_minutes'],
                'packages' => array_map(fn($p) => [
                    'id' => $p->id,
                    'tracking_id' => $p->tracking_id,
                    'dropoff_address' => $p->dropoff_address,
                    'dropoff_lat' => $p->dropoff_lat,
                    'dropoff_lng' => $p->dropoff_lng,
                ], $cluster['packages']),
            ];
        }, $result['clusters']);

        return [
            'groups' => $groups,
            'total_packages' => $result['total_packages'],
            'routed_packages' => $result['routed_packages'],
            'unrouteable_count' => $result['unrouteable']->count(),
            'unrouteable' => $result['unrouteable'],
        ];
    }

    public function optimizeRoute(array $packages, array $params = []): array
    {
        $packageModels = collect($packages)->map(function ($p) {
            if ($p instanceof UrbanGoodzRoutePackage) {
                return $p;
            }
            return UrbanGoodzRoutePackage::find($p['id'] ?? $p['package_id'] ?? null);
        })->filter()->values();

        if ($packageModels->isEmpty()) {
            return [
                'optimized_stops' => [],
                'total_distance_miles' => 0,
                'estimated_duration_minutes' => 0,
            ];
        }

        $clusteringService = app(UrbanGoodzRouteClusteringService::class);

        $clusterResult = $clusteringService->clusterPackages($packageModels, [
            'requested_route_count' => 1,
            'maximum_packages_per_route' => count($packages),
            'start_location' => $params['start_location'] ?? null,
            'end_location' => $params['end_location'] ?? null,
            'respect_time_windows' => true,
        ]);

        if (empty($clusterResult['clusters'])) {
            return [
                'optimized_stops' => [],
                'total_distance_miles' => 0,
                'estimated_duration_minutes' => 0,
            ];
        }

        $firstCluster = $clusterResult['clusters'][0];

        return [
            'optimized_stops' => array_map(function ($pkg) {
                return [
                    'package_id' => $pkg->id,
                    'tracking_id' => $pkg->tracking_id,
                    'dropoff_address' => $pkg->dropoff_address,
                    'dropoff_lat' => $pkg->dropoff_lat,
                    'dropoff_lng' => $pkg->dropoff_lng,
                ];
            }, $firstCluster['packages']),
            'total_distance_miles' => $firstCluster['stats']['estimated_miles'],
            'estimated_duration_minutes' => $firstCluster['stats']['estimated_duration_minutes'],
            'stop_count' => $firstCluster['stats']['package_count'],
        ];
    }

    private function parseJsonResponse(string $result, array $fallback): array
    {
        $trimmed = trim($result);

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $trimmed = substr($trimmed, $start, $end - $start + 1);
        }

        $json = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        Log::warning('BusinessClientAIService: Failed to parse AI JSON response', [
            'error' => json_last_error_msg(),
            'raw' => substr($result, 0, 500),
        ]);

        return $fallback;
    }

    /**
     * Analyzes a set of the client's route batches (already fetched by the
     * caller, with packages/deliveryMan eager-loaded) for on-time, cost, and
     * exception patterns over the given date window.
     */
    public function analyzeRoutePerformance(array $routes, array $options = []): array
    {
        $dateFrom = $options['date_from'] ?? now()->subDays(30)->toDateString();
        $dateTo = $options['date_to'] ?? now()->toDateString();

        $totalRoutes = count($routes);
        $totalPackages = 0;
        $completedPackages = 0;
        $exceptionPackages = 0;
        $driverTotals = [];

        foreach ($routes as $route) {
            $packages = $route['packages'] ?? [];
            $totalPackages += count($packages);
            foreach ($packages as $pkg) {
                if (in_array($pkg['status'] ?? null, ['delivered', 'completed'])) {
                    $completedPackages++;
                }
                if (($pkg['status'] ?? null) === 'exception') {
                    $exceptionPackages++;
                }
            }
            $driverName = trim(($route['delivery_man']['f_name'] ?? '') . ' ' . ($route['delivery_man']['l_name'] ?? ''));
            if ($driverName !== '') {
                $driverTotals[$driverName] = ($driverTotals[$driverName] ?? 0) + count($packages);
            }
        }

        $completionRate = $totalPackages > 0 ? round($completedPackages / $totalPackages * 100, 1) : 0;
        $exceptionRate = $totalPackages > 0 ? round($exceptionPackages / $totalPackages * 100, 1) : 0;

        $systemPrompt = "You are a logistics performance analyst for Urban Goodz.
Analyze this business client's route batch performance for the given period and provide actionable insights.

You MUST return ONLY a valid JSON object with this structure, no markdown, no code fences:
{
  \"summary\": string,
  \"on_time_estimate\": \"strong\"|\"moderate\"|\"needs_attention\",
  \"insights\": [{\"finding\": string, \"impact\": \"high\"|\"medium\"|\"low\", \"recommendation\": string}],
  \"top_drivers_by_volume\": [{\"driver\": string, \"packages\": number}],
  \"risk_flags\": [string]
}";

        $context = [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'total_routes' => $totalRoutes,
            'total_packages' => $totalPackages,
            'completion_rate_percent' => $completionRate,
            'exception_rate_percent' => $exceptionRate,
            'driver_volume' => $driverTotals,
        ];

        $result = $this->ai->chat($systemPrompt, 'Analyze this route batch performance data and provide insights.', $context);

        $parsed = $this->parseJsonResponse($result, [
            'summary' => 'No AI narrative available; showing computed metrics only.',
            'on_time_estimate' => 'unknown',
            'insights' => [],
            'top_drivers_by_volume' => [],
            'risk_flags' => [],
        ]);

        // Computed metrics are real regardless of whether the AI narrative
        // parsed; never let a fallback lose the actual numbers.
        return array_merge($parsed, [
            'total_routes' => $totalRoutes,
            'total_packages' => $totalPackages,
            'completion_rate_percent' => $completionRate,
            'exception_rate_percent' => $exceptionRate,
        ]);
    }

    /**
     * Flags cost outliers by comparing each of the client's rated loads in
     * the lookback window against that lane's own historical average
     * (deterministic statistics — not an LLM guess at what "anomalous"
     * means), then asks the AI service only to narrate the flagged set.
     */
    public function detectCostAnomalies(int $clientId, array $options = []): array
    {
        $lookbackDays = $options['lookback_days'] ?? 30;
        $thresholdPercent = $options['threshold_percent'] ?? 20;

        $loads = \App\Models\UrbanGoodzLoadBoardLoad::where('business_client_id', $clientId)
            ->whereNotNull('payout_amount')
            ->where('payout_amount', '>', 0)
            ->where('created_at', '>=', now()->subDays($lookbackDays))
            ->get(['id', 'load_number', 'origin_state', 'destination_state', 'payout_amount', 'created_at']);

        $laneAverages = $loads->groupBy(fn($l) => ($l->origin_state ?? '?') . '-' . ($l->destination_state ?? '?'))
            ->map(fn($group) => $group->avg('payout_amount'));

        $anomalies = [];
        foreach ($loads as $load) {
            $lane = ($load->origin_state ?? '?') . '-' . ($load->destination_state ?? '?');
            $laneAvg = $laneAverages[$lane] ?? null;
            if ($laneAvg === null || $laneAvg <= 0) {
                continue;
            }
            $deviationPercent = round((($load->payout_amount - $laneAvg) / $laneAvg) * 100, 1);
            if (abs($deviationPercent) >= $thresholdPercent) {
                $anomalies[] = [
                    'load_id' => $load->id,
                    'load_number' => $load->load_number,
                    'lane' => $lane,
                    'payout_amount' => (float) $load->payout_amount,
                    'lane_average' => round($laneAvg, 2),
                    'deviation_percent' => $deviationPercent,
                    'direction' => $deviationPercent > 0 ? 'above_average' : 'below_average',
                ];
            }
        }

        if (empty($anomalies)) {
            return [];
        }

        $systemPrompt = "You are a cost-control analyst for Urban Goodz.
Each item below is a load whose payout already deviates from its own lane's average by at least {$thresholdPercent}%.
Return ONLY a JSON array, no markdown, no code fences, where each element is:
{\"load_id\": number, \"note\": string, \"severity\": \"high\"|\"medium\"|\"low\"}";

        $result = $this->ai->chat($systemPrompt, 'Give a one-sentence note and severity for each flagged load.', ['anomalies' => $anomalies]);
        $notes = $this->parseJsonResponse('{"items":' . $result . '}', ['items' => []]);
        $notesByLoadId = collect($notes['items'] ?? [])->keyBy('load_id');

        return array_map(function ($a) use ($notesByLoadId) {
            $note = $notesByLoadId->get($a['load_id']);
            $a['note'] = $note['note'] ?? null;
            $a['severity'] = $note['severity'] ?? (abs($a['deviation_percent']) >= 50 ? 'high' : 'medium');
            return $a;
        }, $anomalies);
    }
}
