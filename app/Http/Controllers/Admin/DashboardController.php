<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Item;
use App\Models\User;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Store;
use App\Models\Review;
use App\Models\Wishlist;
use App\Scopes\ZoneScope;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\OrderTransaction;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzRentalBooking;
use App\Models\UrbanGoodzLogisticsJob;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzEarnMoneyOpportunity;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzCreatorCampaign;
use App\Models\UrbanGoodzCreatorEarning;
use App\Models\UrbanGoodzCreatorBusinessLead;
use App\Models\UrbanGoodzBusinessClient;
use App\Models\UrbanGoodzCommunityPost;
use App\Models\UrbanGoodzDiscoverySearch;
use App\Models\OrderAnywhereRequest;
use App\Models\MeasurementRequest;
use App\Models\UrbanGoodzRentalAsset;
use App\Models\UrbanGoodzBusinessClientUser;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzDriverEarning;
use App\Models\UrbanGoodzDriverPayoutRequest;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodz\VendorBusinessDirectoryService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\RideShare\Entities\UserManagement\Rider;

class DashboardController extends Controller
{

    public function __construct()
    {
        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));");
            }
        } catch (\Throwable $e) {
            // Suppress if DB connection not ready or statement restricted
        }
    }

    public function user_dashboard(Request $request)
    {
        $params = [
            'zone_id' => $request['zone_id'] ?? 'all',
            'module_id' => Config::get('module.current_module_id'),
            'statistics_type' => $request['statistics_type'] ?? 'overall',
            'user_overview' => $request['user_overview'] ?? 'overall',
            'commission_overview' => $request['commission_overview'] ?? 'this_year',
            'business_overview' => $request['business_overview'] ?? 'overall',
        ];

        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $delivery_commission = $data['delivery_commission'];
        $customers = User::zone($params['zone_id'])->take(2)->get();

        $delivery_man = DeliveryMan::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->limit(2)->get('image');

        $active_deliveryman = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()->Active()->count();

        $inactive_deliveryman = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()->where('application_status', 'approved')->where('active', 0)->count();

        $blocked_deliveryman = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()->where('application_status', 'approved')->where('status', 0)->count();

        $newly_joined_deliveryman = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->where('application_status', 'approved')
            ->Zonewise()->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'))->count();

        $reviews = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->count();

        $positive_reviews = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->whereIn('rating', [4, 5])->get()->count();
        $good_reviews = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->where('rating', 3)->count();
        $neutral_reviews = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->where('rating', 2)->count();
        $negative_reviews = Review::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->whereHas('item.store', function ($query) use ($params) {
                return $query->where('zone_id', $params['zone_id']);
            });
        })->where('rating', 1)->count();

        $from = now()->startOfMonth(); // first date of the current month
        $to = now();
        $this_month = User::zone($params['zone_id'])->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'))->count();
        $number = 12;
        $from = Carbon::now()->startOfYear()->format('Y-m-d');
        $to = Carbon::now()->endOfYear()->format('Y-m-d');

        $last_year_users = User::zone($params['zone_id'])
            ->whereMonth('created_at', 12)
            ->whereYear('created_at', now()->format('Y') - 1)
            ->count();

        $users = User::zone($params['zone_id'])
            ->select(
                DB::raw('(count(id)) as total'),
                DB::raw('YEAR(created_at) year, MONTH(created_at) month')
            )
            ->whereBetween('created_at', [Carbon::parse(now())->startOfYear(), Carbon::parse(now())->endOfYear()])
            ->groupBy('year', 'month')->get()->toArray();

        for ($inc = 1; $inc <= $number; $inc++) {
            $user_data[$inc] = 0;
            foreach ($users as $match) {
                if ($match['month'] == $inc) {
                    $user_data[$inc] = $match['total'];
                }
            }
        }

        $active_customers = User::zone($params['zone_id'])->where('status', 1)->count();
        $blocked_customers = User::zone($params['zone_id'])->where('status', 0)->count();
        $newly_joined = User::zone($params['zone_id'])->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'))->count();

        $employees = Admin::zone()->with(['role'])->where('role_id', '!=', '1')
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->get();

        $deliveryMen = DeliveryMan::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->zonewise()->available()->active()->get();

        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);

        $module_type = Config::get('module.current_module_type');

        if(addon_published_status('RideShare') && Schema::hasTable('riders')) {
            try {
                $rider_data = self::get_rider_data($params);
            } catch (\Throwable $e) {
                $rider_data = [];
            }
        } else {
            $rider_data = [];
        }

        $viewName = (empty($module_type) || !view()->exists("admin-views.dashboard-{$module_type}")) ? "admin-views.dashboard" : "admin-views.dashboard-{$module_type}";
        return view($viewName, compact('data', 'reviews', 'this_month', 'user_data', 'neutral_reviews', 'good_reviews', 'negative_reviews', 'positive_reviews', 'employees', 'active_deliveryman', 'deliveryMen', 'inactive_deliveryman', 'newly_joined_deliveryman', 'delivery_man', 'total_sell', 'commission', 'delivery_commission', 'params', 'module_type', 'customers', 'active_customers', 'blocked_customers', 'newly_joined', 'last_year_users', 'blocked_deliveryman', 'rider_data'));
    }

    private function get_rider_data($params) {

        $data['rider_images'] = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->limit(2)
            ->get('image');

        $data['active_rider'] = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->active()
            ->count();

        $data['inactive_rider'] = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->where('application_status', 'approved')
            ->where('active', 0)
            ->count();

        $data['blocked_rider'] = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->where('application_status', 'approved')
            ->where('status', 0)
            ->count();

        $data['newly_joined_rider'] = Rider::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->where('application_status', 'approved')
            ->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'))
            ->count();

        $data['top_riders'] = Rider::withCount('driverTrips')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->having("driver_trips_count", '>', 0)
            ->orderBy("driver_trips_count", 'desc')
            ->take(6)
            ->get();

        $riders = Rider::with('last_location')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->available()
            ->active()
            ->get();

        $data['map_riders'] = Helpers::deliverymen_list_formatting($riders);

        $data['total_riders'] = $data['active_rider'] + $data['inactive_rider'] + $data['blocked_rider'];

        return $data;

    }

    public function transaction_dashboard(Request $request)
    {
        $module_type = Config::get('module.current_module_type');
        return view("admin-views.dashboard-{$module_type}");
    }

    public function dispatch_dashboard(Request $request)
    {
        $module_type = Config::get('module.current_module_type');

        $params = [
            'zone_id' => $request['zone_id'] ?? 'all',
            'module_id' => Config::get('module.current_module_id'),
            'statistics_type' => $request['statistics_type'] ?? 'overall',
            'user_overview' => $request['user_overview'] ?? 'overall',
            'commission_overview' => $request['commission_overview'] ?? 'this_year',
            'business_overview' => $request['business_overview'] ?? 'overall',
        ];

        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);

        $maxOrder = config('dm_maximum_orders') ?? 1;

        $deliveryman_stats = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->Zonewise()
            ->selectRaw("
                COUNT(*) as total,

                SUM(CASE
                    WHEN active = 1
                    THEN 1 ELSE 0 END
                ) as active_deliveryman,

                SUM(CASE
                    WHEN application_status = 'approved'
                        AND active = 0
                    THEN 1 ELSE 0 END
                ) as inactive_deliveryman,

                SUM(CASE
                    WHEN application_status = 'approved'
                        AND status = 0
                    THEN 1 ELSE 0 END
                ) as suspend_deliveryman,

                SUM(CASE
                    WHEN active = 1
                        AND current_orders > {$maxOrder}
                    THEN 1 ELSE 0 END
                ) as unavailable_deliveryman,

                SUM(CASE
                    WHEN active = 1
                        AND current_orders < {$maxOrder}
                    THEN 1 ELSE 0 END
                ) as available_deliveryman
            ")
            ->first();

        $active_deliveryman      = $deliveryman_stats->active_deliveryman;
        $inactive_deliveryman    = $deliveryman_stats->inactive_deliveryman;
        $suspend_deliveryman     = $deliveryman_stats->suspend_deliveryman;
        $unavailable_deliveryman = $deliveryman_stats->unavailable_deliveryman;
        $available_deliveryman   = $deliveryman_stats->available_deliveryman;


        $deliveryMen = DeliveryMan::when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })->zonewise()->available()->active()->get();

        $deliveryMen = Helpers::deliverymen_list_formatting($deliveryMen);

        $viewName = (empty($module_type) || !view()->exists("admin-views.dashboard-{$module_type}")) ? "admin-views.dashboard-dispatch" : "admin-views.dashboard-{$module_type}";
        return view($viewName, compact('data', 'active_deliveryman', 'deliveryMen', 'unavailable_deliveryman', 'available_deliveryman', 'inactive_deliveryman', 'module_type', 'suspend_deliveryman'));
    }

    public static function urban_goodz_dashboard_data(): array
    {
        if (!auth('admin')->check() || !Helpers::module_permission_check('urban_goodz_view')) {
            return [];
        }

        return [
            'order_anywhere_count' => Schema::hasTable('order_anywhere_requests') ? OrderAnywhereRequest::count() : 0,
            'fashion_fit_count' => Schema::hasTable('urban_goodz_measurement_requests') ? MeasurementRequest::count() : 0,
            'payment_ledgers_count' => Schema::hasTable('urban_goodz_payment_ledgers') ? UrbanGoodzPaymentLedger::count() : 0,
            'rental_assets_count' => Schema::hasTable('urban_goodz_rental_assets') ? UrbanGoodzRentalAsset::count() : 0,
            'rental_bookings_count' => Schema::hasTable('urban_goodz_rental_bookings') ? UrbanGoodzRentalBooking::count() : 0,
            'logistics_jobs_count' => Schema::hasTable('urban_goodz_logistics_jobs') ? UrbanGoodzLogisticsJob::count() : 0,
            'medical_courier_jobs_count' => Schema::hasTable('urban_goodz_medical_courier_jobs') ? UrbanGoodzMedicalCourierJob::count() : 0,
            'events_count' => Schema::hasTable('urban_goodz_events') ? UrbanGoodzEvent::count() : 0,
            'ai_conversations_count' => Schema::hasTable('urban_goodz_ai_conversations') ? UrbanGoodzAIConversation::count() : 0,
            'earn_opportunities_count' => Schema::hasTable('urban_goodz_earn_money_opportunities') ? UrbanGoodzEarnMoneyOpportunity::count() : 0,
            'creator_applications_count' => Schema::hasTable('urban_goodz_creator_applications') ? UrbanGoodzCreatorApplication::count() : 0,
            'creator_campaigns_count' => Schema::hasTable('urban_goodz_creator_campaigns') ? UrbanGoodzCreatorCampaign::count() : 0,
            'creator_revenue' => Schema::hasTable('urban_goodz_creator_earnings') ? UrbanGoodzCreatorEarning::where('status', 'paid')->sum('amount') : 0,
            'creator_business_leads_count' => Schema::hasTable('urban_goodz_creator_business_leads') ? UrbanGoodzCreatorBusinessLead::count() : 0,
            'community_posts_count' => Schema::hasTable('urban_goodz_community_posts') ? UrbanGoodzCommunityPost::count() : 0,
            'discovery_searches_count' => Schema::hasTable('urban_goodz_discovery_searches') ? UrbanGoodzDiscoverySearch::count() : 0,
            'business_clients_count' => Schema::hasTable('urban_goodz_business_clients') ? UrbanGoodzBusinessClient::count() : 0,
            'business_portal_users_count' => Schema::hasTable('urban_goodz_business_client_users') ? UrbanGoodzBusinessClientUser::count() : 0,
            'dedicated_routes_count' => Schema::hasTable('urban_goodz_dedicated_routes') ? UrbanGoodzDedicatedRoute::count() : 0,
            'route_packages_count' => Schema::hasTable('urban_goodz_route_packages') ? UrbanGoodzRoutePackage::count() : 0,
            'driver_earnings_count' => Schema::hasTable('urban_goodz_driver_earnings') ? UrbanGoodzDriverEarning::count() : 0,
            'driver_payouts_count' => Schema::hasTable('urban_goodz_driver_payout_requests') ? UrbanGoodzDriverPayoutRequest::count() : 0,
            'total_revenue' => Schema::hasTable('urban_goodz_payment_ledgers')
                ? UrbanGoodzPaymentLedger::where('event_type', 'capture')->sum('amount') : 0,
            'pending_refunds' => Schema::hasTable('urban_goodz_payment_ledgers')
                ? UrbanGoodzPaymentLedger::where('event_type', 'refund')->where('payment_status', 'pending')->count() : 0,
            'load_board_count' => Schema::hasTable('urban_goodz_load_board_loads') ? UrbanGoodzLoadBoardLoad::count() : 0,
        ];
    }

    public static function ug_insights(array $data = [], array $ugData = [], array $directory = []): array
    {
        $insights = [];

        $pendingRefunds = (int) ($ugData['pending_refunds'] ?? 0);
        if ($pendingRefunds > 0) {
            $insights[] = [
                'tone' => 'warn',
                'text' => $pendingRefunds.' pending refund'.($pendingRefunds > 1 ? 's' : '').' need attention in the Payment Center.',
                'href' => route('admin.urban-goodz.payments.index'),
                'action' => 'Review',
            ];
        }

        $orphaned = (int) ($directory['orphaned_vendors'] ?? 0);
        if ($orphaned > 0) {
            $insights[] = [
                'tone' => 'bad',
                'text' => $orphaned.' vendor account'.($orphaned > 1 ? 's have' : ' has').' no store attached yet.',
                'href' => route('admin.urban-goodz.vendors.index', ['tab' => 'missing-store']),
                'action' => 'Resolve',
            ];
        }

        $unassigned = (int) ($data['searching_for_dm'] ?? 0);
        if ($unassigned > 0) {
            $insights[] = [
                'tone' => 'warn',
                'text' => $unassigned.' order'.($unassigned > 1 ? 's are' : ' is').' waiting for dispatch assignment.',
                'href' => route('admin.urban-goodz.dispatcher-sourcing.dashboard'),
                'action' => 'Dispatch',
            ];
        }

        $activeVendors = (int) ($directory['active_vendors'] ?? 0);
        $activeStores = (int) ($directory['active_stores'] ?? 0);
        if ($activeVendors > 0 || $activeStores > 0) {
            $insights[] = [
                'tone' => 'good',
                'text' => $activeVendors.' active vendor'.($activeVendors === 1 ? '' : 's').' across '.$activeStores.' live store'.($activeStores === 1 ? '' : 's').' on the marketplace.',
                'href' => route('admin.urban-goodz.vendors.index', ['tab' => 'active-stores']),
                'action' => 'View',
            ];
        }

        return $insights;
    }

    public function ug_live_feed(Request $request)
    {
        $feed = [];

        try {
            if (Schema::hasTable('orders')) {
                $orders = Order::with('store:id,name')->latest()->limit(5)->get();
                foreach ($orders as $o) {
                    $feed[] = [
                        'time' => optional($o->created_at)->diffForHumans() ?? '',
                        'tone' => in_array($o->order_status, ['failed', 'canceled', 'refunded', 'refund_requested']) ? 'bad' : 'info',
                        'icon' => 'tio-shopping-cart-outlined',
                        'title' => 'Order #'.$o->id,
                        'meta' => ucwords(str_replace('_', ' ', (string) $o->order_status)).($o->store?->name ? ' · '.$o->store->name : ''),
                        'href' => route('admin.order.details', ['id' => $o->id]),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Keep the live feed resilient against schema drift.
        }

        try {
            if (Schema::hasTable('urban_goodz_payment_ledgers')) {
                $ledgers = UrbanGoodzPaymentLedger::latest()->limit(4)->get();
                foreach ($ledgers as $l) {
                    $feed[] = [
                        'time' => optional($l->created_at)->diffForHumans() ?? '',
                        'tone' => $l->event_type === 'refund' ? 'warn' : 'good',
                        'icon' => 'tio-dollar-outlined',
                        'title' => (string) $l->ledger_number,
                        'meta' => ucwords(str_replace('_', ' ', (string) $l->feature)).' · '.Helpers::format_currency((float) $l->amount).' · '.ucwords(str_replace('_', ' ', (string) $l->payment_status)),
                        'href' => route('admin.urban-goodz.payments.index'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Keep the live feed resilient against schema drift.
        }

        try {
            if (Schema::hasTable('urban_goodz_business_clients')) {
                $clients = UrbanGoodzBusinessClient::latest()->limit(3)->get();
                foreach ($clients as $c) {
                    $feed[] = [
                        'time' => optional($c->created_at)->diffForHumans() ?? '',
                        'tone' => 'dijon',
                        'icon' => 'tio-briefcase',
                        'title' => (string) $c->company_name,
                        'meta' => 'New business client'.($c->contact_name ? ' · '.$c->contact_name : ''),
                        'href' => route('admin.urban-goodz.business-clients.index'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Keep the live feed resilient against schema drift.
        }

        try {
            if (Schema::hasTable('order_anywhere_requests')) {
                $requests = OrderAnywhereRequest::latest()->limit(3)->get();
                foreach ($requests as $r) {
                    $feed[] = [
                        'time' => optional($r->created_at)->diffForHumans() ?? '',
                        'tone' => 'info',
                        'icon' => 'tio-shop',
                        'title' => 'Order Anywhere '.($r->request_number ?? '#'.$r->id),
                        'meta' => ucwords(str_replace('_', ' ', (string) $r->status)).($r->customer_name ? ' · '.$r->customer_name : ''),
                        'href' => route('admin.urban-goodz.order-anywhere.index'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Keep the live feed resilient against schema drift.
        }

        try {
            if (Schema::hasTable('urban_goodz_driver_payout_requests')) {
                $payouts = UrbanGoodzDriverPayoutRequest::latest()->limit(3)->get();
                foreach ($payouts as $p) {
                    $feed[] = [
                        'time' => optional($p->created_at)->diffForHumans() ?? '',
                        'tone' => 'black',
                        'icon' => 'tio-money',
                        'title' => 'Driver payout '.Helpers::format_currency((float) $p->requested_amount),
                        'meta' => ucwords(str_replace('_', ' ', (string) $p->status)),
                        'href' => route('admin.urban-goodz.driver-payouts.index'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Keep the live feed resilient against schema drift.
        }

        $ugData = self::urban_goodz_dashboard_data();
        $directory = $ugData ? app(VendorBusinessDirectoryService::class)->summary() : [];
        $orderStats = [];
        try {
            $params = session('dash_params', ['zone_id' => 'all', 'module_id' => Config::get('module.current_module_id')]);
            $orderStats = self::order_stats_calc($params['zone_id'] ?? 'all', $params['module_id'] ?? null);
        } catch (\Throwable $e) {
            // Fall back to an empty insight set rather than failing the feed.
        }

        return response()->json([
            'server_time' => now()->format('H:i:s'),
            'ugData' => $ugData,
            'insights' => self::ug_insights($orderStats, $ugData, $directory),
            'feed' => $feed,
        ]);
    }

    public function dashboard(Request $request)
    {
        $params = [
            'zone_id' => $request['zone_id'] ?? 'all',
            'module_id' => Config::get('module.current_module_id'),
            'statistics_type' => $request['statistics_type'] ?? 'overall',
            'user_overview' => $request['user_overview'] ?? 'overall',
            'commission_overview' => $request['commission_overview'] ?? 'this_year',
            'business_overview' => $request['business_overview'] ?? 'overall',
        ];
        session()->put('dash_params', $params);
        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $delivery_commission = $data['delivery_commission'];
        $label = $data['label'];
        $module_type = Config::get('module.current_module_type');

        $ugData = self::urban_goodz_dashboard_data();
        $vendorDirectorySummary = $ugData
            ? app(VendorBusinessDirectoryService::class)->summary()
            : [];
        $insights = self::ug_insights($data, $ugData, $vendorDirectorySummary);

        if ($module_type == 'settings') {
            if (auth('admin')->check() && auth('admin')->user()->role_id == 1) {
                return view("admin-views.dashboard", compact('data', 'total_sell', 'commission', 'delivery_commission', 'label', 'params', 'module_type', 'ugData', 'vendorDirectorySummary', 'insights'));
            }
            return redirect()->route('admin.business-settings.business-setup');
        }
        if ($module_type == 'ride-share' && addon_published_status('RideShare') == 1) {
            return redirect()->route('admin.ride-share.dashboard');
        } elseif ($module_type == 'ride-share' && addon_published_status('RideShare') == 0) {
            return view('errors.404');
        }
        if ($module_type == 'rental' && addon_published_status('Rental') == 1) {
            return redirect()->route('admin.rental.dashboard');
        }
        if ($module_type == 'rental' && addon_published_status('Rental') == 0) {
            return view('errors.404');
        }
        $viewName = (empty($module_type) || !view()->exists("admin-views.dashboard-{$module_type}")) ? "admin-views.dashboard" : "admin-views.dashboard-{$module_type}";
        return view($viewName, compact('data', 'total_sell', 'commission', 'delivery_commission', 'label', 'params', 'module_type', 'ugData', 'vendorDirectorySummary', 'insights'));
    }

    public function order(Request $request)
    {
        $params = session('dash_params');
        foreach ($params as $key => $value) {
            if ($key == 'statistics_type') {
                $params['statistics_type'] = $request['statistics_type'];
            }
        }
        session()->put('dash_params', $params);

        if ($params['zone_id'] != 'all') {
            $store_ids = Store::where(['module_id' => $params['module_id']])->where(['zone_id' => $params['zone_id']])->pluck('id')->toArray();
        } else {
            $store_ids = Store::where(['module_id' => $params['module_id']])->pluck('id')->toArray();
        }
        $data = self::order_stats_calc($params['zone_id'], $params['module_id']);
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'parcel') {
            return response()->json([
                'view' => view('admin-views.partials._dashboard-order-stats-parcel', compact('data'))->render()
            ], 200);
        } elseif ($module_type == 'food') {
            return response()->json([
                'view' => view('admin-views.partials._dashboard-order-stats-food', compact('data'))->render()
            ], 200);
        }
        return response()->json([
            'view' => view('admin-views.partials._dashboard-order-stats', compact('data'))->render()
        ], 200);
    }

    public function zone(Request $request)
    {
        $params = session('dash_params');
        foreach ($params as $key => $value) {
            if ($key == 'zone_id') {
                $params['zone_id'] = $request['zone_id'];
            }
        }
        session()->put('dash_params', $params);

        $data = self::dashboard_data($request);
        $total_sell = $data['total_sell'];
        $commission = $data['commission'];
        $popular = $data['popular'];
        $top_deliveryman = $data['top_deliveryman'];
        $top_rated_foods = $data['top_rated_foods'];
        $top_restaurants = $data['top_restaurants'];
        $top_customers = $data['top_customers'];
        $top_sell = $data['top_sell'];
        $delivery_commission = $data['delivery_commission'];
        $module_type = Config::get('module.current_module_type');
        $label = $data['label'];

        return response()->json([
            'popular_restaurants' => view('admin-views.partials._popular-restaurants', compact('popular'))->render(),
            'top_deliveryman' => view('admin-views.partials._top-deliveryman', compact('top_deliveryman'))->render(),
            'top_rated_foods' => view('admin-views.partials._top-rated-foods', compact('top_rated_foods'))->render(),
            'top_restaurants' => view('admin-views.partials._top-restaurants', compact('top_restaurants'))->render(),
            'top_customers' => view('admin-views.partials._top-customer', compact('top_customers'))->render(),
            'top_selling_foods' => view('admin-views.partials._top-selling-foods', compact('top_sell'))->render(),


            'user_overview' => view('admin-views.partials._user-overview-chart', compact('data'))->render(),
            'monthly_graph' => view('admin-views.partials._monthly-earning-graph', compact('total_sell', 'commission', 'delivery_commission', 'label'))->render(),
            'stat_zone' => view('admin-views.partials._zone-change', compact('data'))->render(),
            'order_stats' => $module_type == 'parcel' ? view('admin-views.partials._dashboard-order-stats-parcel', compact('data'))->render() :
                ($module_type == 'food' ? view('admin-views.partials._dashboard-order-stats-food', compact('data'))->render() :
                    view('admin-views.partials._dashboard-order-stats', compact('data'))->render()),
        ], 200);
    }

    public function user_overview(Request $request)
    {
        $params = session('dash_params');
        foreach ($params as $key => $value) {
            if ($key == 'user_overview') {
                $params['user_overview'] = $request['user_overview'];
            }
        }
        session()->put('dash_params', $params);

        $data = self::user_overview_calc($params['zone_id'], $params['module_id']);
        $module_type = Config::get('module.current_module_type');
        if ($module_type == 'parcel') {
            return response()->json([
                'view' => view('admin-views.partials._user-overview-chart-parcel', compact('data'))->render()
            ], 200);
        }

        return response()->json([
            'view' => view('admin-views.partials._user-overview-chart', compact('data'))->render()
        ], 200);
    }

    public function commission_overview(Request $request)
    {
        $params = session('dash_params');
        foreach ($params as $key => $value) {
            if ($key == 'commission_overview') {
                $params['commission_overview'] = $request['commission_overview'];
            }
        }
        session()->put('dash_params', $params);

        $data = self::dashboard_data($request);

        return response()->json([
            'view' => view('admin-views.partials._commission-overview-chart', compact('data'))->render(),
            'gross_sale' => view('admin-views.partials._gross_sale', compact('data'))->render()
        ], 200);
    }

    /**
     * The doughnut chart partial (_business-overview-chart) has existed
     * since this dashboard page was built and already expects data['food'],
     * data['reviews'], data['wishlist'] - the calc feeding it was never
     * written, so the route pointed at a method that didn't exist.
     */
    public function business_overview(Request $request)
    {
        $params = session('dash_params');
        foreach ($params as $key => $value) {
            if ($key == 'business_overview') {
                $params['business_overview'] = $request['business_overview'];
            }
        }
        session()->put('dash_params', $params);

        $data = self::business_overview_calc($params['zone_id'], $params['module_id']);

        return response()->json([
            'view' => view('admin-views.partials._business-overview-chart', compact('data'))->render(),
        ], 200);
    }

    public function business_overview_calc($zone_id, $module_id)
    {
        $storeIds = Store::when(is_numeric($zone_id), function ($q) use ($zone_id) {
            return $q->where('zone_id', $zone_id);
        })->where('module_id', $module_id)->pluck('id');

        $food = Item::where('module_id', $module_id)
            ->when(is_numeric($zone_id), function ($q) use ($storeIds) {
                return $q->whereIn('store_id', $storeIds);
            })->count();

        $reviews = Review::module($module_id)
            ->when(is_numeric($zone_id), function ($q) use ($storeIds) {
                return $q->whereIn('store_id', $storeIds);
            })->count();

        $wishlist = Wishlist::whereHas('item', function ($q) use ($module_id, $zone_id, $storeIds) {
            $q->where('module_id', $module_id);
            if (is_numeric($zone_id)) {
                $q->whereIn('store_id', $storeIds);
            }
        })->count();

        return ['food' => $food, 'reviews' => $reviews, 'wishlist' => $wishlist];
    }

    public function order_stats_calc($zone_id, $module_id)
    {
        $params = session('dash_params');
        $module_type = Config::get('module.current_module_type');

        if ($module_id && $params['statistics_type'] == 'today') {
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereDate('created_at', Carbon::now());
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereDate('accepted', Carbon::now());
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereDate('processing', Carbon::now());
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereDate('picked_up', Carbon::now());
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereDate('delivered', Carbon::now());
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereDate('canceled', Carbon::now());
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereDate('refund_requested', Carbon::now());
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereDate('refunded', Carbon::now());
            $new_orders = Order::where('module_id', $module_id)->whereDate('schedule_at', Carbon::now());
            $new_items = Item::where('is_approved', 1)->where('module_id', $module_id)->whereDate('created_at', Carbon::now());
            $new_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereDate('created_at', Carbon::now());
            $new_customers = User::whereDate('created_at', Carbon::now());
            if ($module_type == 'parcel') {
                $total_orders = Order::where('module_id', $module_id)->whereDate('created_at', Carbon::now());
            } else {
                $total_orders = Order::where('module_id', $module_id);
            }
            $total_items = Item::where('is_approved', 1)->where('module_id', $module_id);
            $total_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id);
            $total_customers = User::query();
        } elseif ($module_id && $params['statistics_type'] == 'this_year') {
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereYear('created_at', now()->format('Y'));
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereYear('accepted', now()->format('Y'));
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereYear('processing', now()->format('Y'));
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereYear('picked_up', now()->format('Y'));
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereYear('delivered', now()->format('Y'));
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereYear('canceled', now()->format('Y'));
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereYear('refund_requested', now()->format('Y'));
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereYear('refunded', now()->format('Y'));
            $new_orders = Order::where('module_id', $module_id)->whereYear('schedule_at', now()->format('Y'));
            $new_items = Item::where('is_approved', 1)->where('module_id', $module_id)->whereYear('created_at', now()->format('Y'));
            $new_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereYear('created_at', now()->format('Y'));
            $new_customers = User::whereYear('created_at', now()->format('Y'));
            $total_orders = Order::where('module_id', $module_id);
            $total_items = Item::where('is_approved', 1)->where('module_id', $module_id);
            $total_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id);
            $total_customers = User::query();
        } elseif ($module_id && $params['statistics_type'] == 'this_month') {
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereMonth('accepted', now()->format('m'))->whereYear('accepted', now()->format('Y'));
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereMonth('processing', now()->format('m'))->whereYear('processing', now()->format('Y'));
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereMonth('picked_up', now()->format('m'))->whereYear('picked_up', now()->format('Y'));
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereMonth('delivered', now()->format('m'))->whereYear('delivered', now()->format('Y'));
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereMonth('canceled', now()->format('m'))->whereYear('canceled', now()->format('Y'));
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereMonth('refund_requested', now()->format('m'))->whereYear('refund_requested', now()->format('Y'));
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereMonth('refunded', now()->format('m'))->whereYear('refunded', now()->format('Y'));
            $new_orders = Order::where('module_id', $module_id)->whereMonth('schedule_at', now()->format('m'))->whereYear('schedule_at', now()->format('Y'));
            $new_items = Item::where('is_approved', 1)->where('module_id', $module_id)->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            $new_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            $new_customers = User::whereMonth('created_at', now()->format('m'))->whereYear('created_at', now()->format('Y'));
            $total_orders = Order::where('module_id', $module_id);
            $total_items = Item::where('is_approved', 1)->where('module_id', $module_id);
            $total_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id);
            $total_customers = User::query();
        } elseif ($module_id && $params['statistics_type'] == 'this_week') {
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id)->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id)->whereBetween('accepted', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id)->whereBetween('processing', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id)->whereBetween('picked_up', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $delivered = Order::Delivered()->where('module_id', $module_id)->whereBetween('delivered', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $canceled = Order::where('module_id', $module_id)->where(['order_status' => 'canceled'])->whereBetween('canceled', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $refund_requested = Order::where('module_id', $module_id)->where(['order_status' => 'refund_requested'])->whereBetween('refund_requested', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $refunded = Order::where('module_id', $module_id)->where(['order_status' => 'refunded'])->whereBetween('refunded', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $new_orders = Order::where('module_id', $module_id)->whereBetween('schedule_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $new_items = Item::where('is_approved', 1)->where('module_id', $module_id)->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $new_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $new_customers = User::whereBetween('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')]);
            $total_orders = Order::where('module_id', $module_id);
            $total_items = Item::where('is_approved', 1)->where('module_id', $module_id);
            $total_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id);
            $total_customers = User::query();
        } elseif ($module_id) {
            $searching_for_dm = Order::SearchingForDeliveryman()->where('module_id', $module_id);
            $accepted_by_dm = Order::AccepteByDeliveryman()->where('module_id', $module_id);
            $preparing_in_rs = Order::Preparing()->where('module_id', $module_id);
            $picked_up = Order::ItemOnTheWay()->where('module_id', $module_id);
            $delivered = Order::Delivered()->where('module_id', $module_id);
            $canceled = Order::Canceled()->where('module_id', $module_id);
            $refund_requested = Order::failed()->where('module_id', $module_id);
            $refunded = Order::Refunded()->where('module_id', $module_id);
            $new_orders = Order::where('module_id', $module_id)->whereDate('schedule_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $new_items = Item::where('is_approved', 1)->where('module_id', $module_id)->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $new_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $new_customers = User::whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $total_orders = Order::where('module_id', $module_id);
            $total_items = Item::where('is_approved', 1)->where('module_id', $module_id);
            $total_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id);
            $total_customers = User::query();
        } else {
            $searching_for_dm = Order::SearchingForDeliveryman();
            $accepted_by_dm = Order::AccepteByDeliveryman();
            $preparing_in_rs = Order::Preparing();
            $picked_up = Order::ItemOnTheWay();
            $delivered = Order::Delivered();
            $canceled = Order::Canceled();
            $refund_requested = Order::failed();
            $refunded = Order::Refunded();
            $new_orders = Order::whereDate('schedule_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $new_items = Item::where('is_approved', 1)->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $new_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $new_customers = User::whereDate('created_at', '>=', now()->subDays(30)->format('Y-m-d'));
            $total_orders = Order::query();
            $total_items = Item::where('is_approved', 1);
            $total_stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1));
            $total_customers = User::query();
        }

        if (is_numeric($zone_id) && $module_id && !in_array($module_type, ['parcel'])) {
            $searching_for_dm = $searching_for_dm->StoreOrder()->OrderScheduledIn(30)->where('zone_id', $zone_id)->count();
            $accepted_by_dm = $accepted_by_dm->StoreOrder()->where('zone_id', $zone_id)->count();
            $preparing_in_rs = $preparing_in_rs->StoreOrder()->where('zone_id', $zone_id)->count();
            $picked_up = $picked_up->StoreOrder()->where('zone_id', $zone_id)->count();
            $delivered = $delivered->StoreOrder()->where('zone_id', $zone_id)->count();
            $canceled = $canceled->StoreOrder()->where('zone_id', $zone_id)->count();
            $refund_requested = $refund_requested->StoreOrder()->where('zone_id', $zone_id)->count();
            $refunded = $refunded->StoreOrder()->where('zone_id', $zone_id)->count();
            $total_orders = $total_orders->StoreOrder()->where('zone_id', $zone_id)->count();
            $total_items = $total_items->count();
            $total_stores = $total_stores->where('zone_id', $zone_id)->count();
            $total_customers = $total_customers->count();
            $new_orders = $new_orders->StoreOrder()->where('zone_id', $zone_id)->count();
            $new_items = $new_items->count();
            $new_stores = $new_stores->where('zone_id', $zone_id)->count();
            $new_customers = $new_customers->count();
        } elseif ($module_id && $module_type != 'parcel') {
            $searching_for_dm = $searching_for_dm->StoreOrder()->OrderScheduledIn(30)->count();
            $accepted_by_dm = $accepted_by_dm->StoreOrder()->count();
            $preparing_in_rs = $preparing_in_rs->StoreOrder()->count();
            $picked_up = $picked_up->StoreOrder()->count();
            $delivered = $delivered->StoreOrder()->count();
            $canceled = $canceled->StoreOrder()->count();
            $refund_requested = $refund_requested->StoreOrder()->count();
            $refunded = $refunded->StoreOrder()->count();
            $total_orders = $total_orders->StoreOrder()->count();
            $total_items = $total_items->count();
            $total_stores = $total_stores->count();
            $total_customers = $total_customers->count();
            $new_orders = $new_orders->StoreOrder()->count();
            $new_items = $new_items->count();
            $new_stores = $new_stores->count();
            $new_customers = $new_customers->count();
        } elseif (is_numeric($zone_id) && $module_id && $module_type == 'parcel') {
            $searching_for_dm = $searching_for_dm->ParcelOrder()->OrderScheduledIn(30)->where('zone_id', $zone_id)->count();
            $accepted_by_dm = $accepted_by_dm->ParcelOrder()->where('zone_id', $zone_id)->count();
            $preparing_in_rs = $preparing_in_rs->ParcelOrder()->where('zone_id', $zone_id)->count();
            $picked_up = $picked_up->ParcelOrder()->where('zone_id', $zone_id)->count();
            $delivered = $delivered->ParcelOrder()->where('zone_id', $zone_id)->count();
            $canceled = $canceled->ParcelOrder()->where('zone_id', $zone_id)->count();
            $refund_requested = $refund_requested->ParcelOrder()->where('zone_id', $zone_id)->count();
            $refunded = $refunded->ParcelOrder()->where('zone_id', $zone_id)->count();
            $total_orders = $total_orders->ParcelOrder()->where('zone_id', $zone_id)->count();
            $total_items = $total_items->count();
            $total_stores = $total_stores->where('zone_id', $zone_id)->count();
            $total_customers = $total_customers->where('zone_id', $zone_id)->count();
            $new_orders = $new_orders->ParcelOrder()->where('zone_id', $zone_id)->count();
            $new_items = $new_items->count();
            $new_stores = $new_stores->where('zone_id', $zone_id)->count();
            $new_customers = $new_customers->where('zone_id', $zone_id)->count();
        } elseif ($module_id && $module_type == 'parcel') {
            $searching_for_dm = $searching_for_dm->ParcelOrder()->OrderScheduledIn(30)->count();
            $accepted_by_dm = $accepted_by_dm->ParcelOrder()->count();
            $preparing_in_rs = $preparing_in_rs->ParcelOrder()->count();
            $picked_up = $picked_up->ParcelOrder()->count();
            $delivered = $delivered->ParcelOrder()->count();
            $canceled = $canceled->ParcelOrder()->count();
            $refund_requested = $refund_requested->ParcelOrder()->count();
            $refunded = $refunded->ParcelOrder()->count();
            $total_orders = $total_orders->ParcelOrder()->count();
            $total_items = $total_items->count();
            $total_stores = $total_stores->count();
            $total_customers = $total_customers->count();
            $new_orders = $new_orders->ParcelOrder()->count();
            $new_items = $new_items->count();
            $new_stores = $new_stores->count();
            $new_customers = $new_customers->count();
        } else {
            $searching_for_dm = $searching_for_dm->StoreOrder()->OrderScheduledIn(30)->count();
            $accepted_by_dm = $accepted_by_dm->StoreOrder()->count();
            $preparing_in_rs = $preparing_in_rs->StoreOrder()->count();
            $picked_up = $picked_up->StoreOrder()->count();
            $delivered = $delivered->StoreOrder()->count();
            $canceled = $canceled->StoreOrder()->count();
            $refund_requested = $refund_requested->StoreOrder()->count();
            $refunded = $refunded->StoreOrder()->count();
            $total_orders = $total_orders->count();
            $total_items = $total_items->count();
            $total_stores = $total_stores->count();
            $total_customers = $total_customers->count();
            $new_orders = $new_orders->count();
            $new_items = $new_items->count();
            $new_stores = $new_stores->count();
            $new_customers = $new_customers->count();
        }
        $data = [
            'searching_for_dm' => $searching_for_dm,
            'accepted_by_dm' => $accepted_by_dm,
            'preparing_in_rs' => $preparing_in_rs,
            'picked_up' => $picked_up,
            'delivered' => $delivered,
            'canceled' => $canceled,
            'refund_requested' => $refund_requested,
            'refunded' => $refunded,
            'total_orders' => $total_orders,
            'total_items' => $total_items,
            'total_stores' => $total_stores,
            'total_customers' => $total_customers,
            'new_orders' => $new_orders,
            'new_items' => $new_items,
            'new_stores' => $new_stores,
            'new_customers' => $new_customers,
        ];

        return $data;
    }

    public function user_overview_calc($zone_id, $module_id)
    {
        $params = session('dash_params');
        //zone
        if (is_numeric($zone_id)) {
            $customer = User::where('zone_id', $zone_id);
            $stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->where(['zone_id' => $zone_id]);
            $delivery_man = DeliveryMan::where('application_status', 'approved')->where('zone_id', $zone_id)->Zonewise();
        } else {
            $customer = User::whereNotNull('id');
            $stores = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->where('module_id', $module_id)->whereNotNull('id');
            $delivery_man = DeliveryMan::where('application_status', 'approved')->Zonewise();
        }
        //user overview
        if ($params['user_overview'] == 'overall') {
            $customer = $customer->count();
            $stores = $stores->count();
            $delivery_man = $delivery_man->count();
        } elseif ($params['user_overview'] == 'this_month') {
            $customer = $customer->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))->count();
            $stores = $stores->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))->count();
            $delivery_man = $delivery_man->whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))->count();
        } elseif ($params['user_overview'] == 'this_year') {
            $customer = $customer
                ->whereYear('created_at', date('Y'))->count();
            $stores = $stores
                ->whereYear('created_at', date('Y'))->count();
            $delivery_man = $delivery_man
                ->whereYear('created_at', date('Y'))->count();
        } else {
            $customer = $customer->whereDate('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')])->count();
            $stores = $stores->whereDate('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')])->count();
            $delivery_man = $delivery_man->whereDate('created_at', [now()->startOfWeek()->format('Y-m-d H:i:s'), now()->endOfWeek()->format('Y-m-d H:i:s')])->count();
        }
        $data = [
            'customer' => $customer,
            'stores' => $stores,
            'delivery_man' => $delivery_man
        ];
        return $data;
    }


    public function dashboard_data($request)
    {
        $params = session('dash_params');
        if (!url()->current() == $request->is('admin/users')) {
            $data_os = self::order_stats_calc($params['zone_id'], $params['module_id']);
            if(Route::currentRouteName() == 'admin.dispatch.dashboard'){
                return $data_os;
            }
            $data_uo = self::user_overview_calc($params['zone_id'], $params['module_id']);
        }
        $popular = Wishlist::with(['store'])
            ->whereHas('store')
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('zone_id', $params['zone_id']);
                });
            })
            ->select('store_id', DB::raw('COUNT(store_id) as count'))->groupBy('store_id')
            ->having("count", '>', 0)
            ->orderBy('count', 'DESC')
            ->limit(6)->get();
        $top_sell = Item::withoutGlobalScope(ZoneScope::class)
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id'])->where('zone_id', $params['zone_id']);
                });
            })
            ->having("order_count", '>', 0)
            ->orderBy("order_count", 'desc')
            ->take(6)
            ->get();
        $top_rated_foods = Item::withoutGlobalScope(ZoneScope::class)
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('module_id', $params['module_id']);
                });
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->whereHas('store', function ($query) use ($params) {
                    return $query->where('zone_id', $params['zone_id']);
                });
            })
            ->having("rating_count", '>', 0)
            ->orderBy('rating_count', 'desc')
            ->take(6)
            ->get();

        $top_deliveryman = DeliveryMan::withCount('orders')->when(is_numeric($params['zone_id']), function ($q) use ($params) {
            return $q->where('zone_id', $params['zone_id']);
        })
            ->Zonewise()
            ->having("orders_count", '>', 0)
            ->orderBy("orders_count", 'desc')
            ->take(6)
            ->get();

        $top_customers = User::when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->withCount([
                'orders as order_count' => fn($query) => $query->where('order_status', 'delivered')
            ])
            ->having('order_count', '>', 0)
            ->orderByDesc('order_count')
            ->take(6)
            ->get();

        $top_restaurants = Store::whereHas('vendor', fn($query) => $query->where('status', 1))->when(is_numeric($params['module_id']), function ($q) use ($params) {
            return $q->where('module_id', $params['module_id']);
        })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            })
            ->having("order_count", '>', 0)
            ->orderBy("order_count", 'desc')
            ->take(6)
            ->get();


        // custom filtering for bar chart
        $months = array(
            '"' . translate('Jan') . '"',
            '"' . translate('Feb') . '"',
            '"' . translate('Mar') . '"',
            '"' . translate('Apr') . '"',
            '"' . translate('May') . '"',
            '"' . translate('Jun') . '"',
            '"' . translate('Jul') . '"',
            '"' . translate('Aug') . '"',
            '"' . translate('Sep') . '"',
            '"' . translate('Oct') . '"',
            '"' . translate('Nov') . '"',
            '"' . translate('Dec') . '"'
        );
        $days = array(
            '"' . translate('Mon') . '"',
            '"' . translate('Tue') . '"',
            '"' . translate('Wed') . '"',
            '"' . translate('Thu') . '"',
            '"' . translate('Fri') . '"',
            '"' . translate('Sat') . '"',
            '"' . translate('Sun') . '"',
        );
        $total_sell = [];
        $commission = [];
        $label = [];
        $query = OrderTransaction::NotRefunded()
            ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                return $q->where('module_id', $params['module_id']);
            })
            ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                return $q->where('zone_id', $params['zone_id']);
            });
        switch ($params['commission_overview']) {
            case "this_year":
                for ($i = 1; $i <= 12; $i++) {
                    $total_sell[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                        ->sum('order_amount');

                    $commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                        ->sum(DB::raw('admin_commission + admin_expense - delivery_fee_comission'));

                    $delivery_commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                        ->sum('delivery_fee_comission');
                }
                $label = $months;
                break;

            case "this_week":
                $weekStartDate = now()->startOfWeek(); // Start from Monday

                for ($i = 0; $i < 7; $i++) { // Loop through each day of the week
                    $currentDate = $weekStartDate->copy()->addDays($i); // Get the date for the current day in the loop

                    $total_sell[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereDate('created_at', $currentDate->format('Y-m-d'))
                        ->sum('order_amount');

                    $commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereDate('created_at', $currentDate->format('Y-m-d'))
                        ->sum(DB::raw('admin_commission + admin_expense - delivery_fee_comission'));

                    $delivery_commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereDate('created_at', $currentDate->format('Y-m-d'))
                        ->sum('delivery_fee_comission');
                }

                $label = $days;
                break;

            case "this_month":
                $start = now()->startOfMonth();
                $total_days = now()->daysInMonth;
                $weeks = array(
                    '"Day 1-7"',
                    '"Day 8-14"',
                    '"Day 15-21"',
                    '"Day 22-' . $total_days . '"',
                );

                for ($i = 1; $i <= 4; $i++) {
                    $end = $start->copy()->addDays(6); // Set the end date for each week

                    // Adjust for the last week of the month
                    if ($i == 4) {
                        $end = now()->endOfMonth();
                    }

                    $total_sell[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereBetween('created_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                        ->sum('order_amount');

                    $commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereBetween('created_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                        ->sum(DB::raw('admin_commission + admin_expense - delivery_fee_comission'));

                    $delivery_commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereBetween('created_at', ["{$start->format('Y-m-d')} 00:00:00", "{$end->format('Y-m-d')} 23:59:59"])
                        ->sum('delivery_fee_comission');

                    // Move to the next week
                    $start = $end->copy()->addDay();
                }

                $label = $weeks;
                break;

            default:
                for ($i = 1; $i <= 12; $i++) {
                    $total_sell[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                        ->sum('order_amount');

                    $commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                        ->sum(DB::raw('admin_commission + admin_expense - delivery_fee_comission'));

                    $delivery_commission[$i] = OrderTransaction::NotRefunded()
                        ->when(is_numeric($params['module_id']), function ($q) use ($params) {
                            return $q->where('module_id', $params['module_id']);
                        })
                        ->when(is_numeric($params['zone_id']), function ($q) use ($params) {
                            return $q->where('zone_id', $params['zone_id']);
                        })
                        ->whereMonth('created_at', $i)->whereYear('created_at', now()->format('Y'))
                        ->sum('delivery_fee_comission');
                }
                $label = $months;
        }

        if (!url()->current() == $request->is('admin/users')) {
            $dash_data = array_merge($data_os, $data_uo);
        }

        $dash_data['popular'] = $popular;
        $dash_data['top_sell'] = $top_sell;
        $dash_data['top_rated_foods'] = $top_rated_foods;
        $dash_data['top_deliveryman'] = $top_deliveryman;
        $dash_data['top_restaurants'] = $top_restaurants;
        $dash_data['top_customers'] = $top_customers;
        $dash_data['total_sell'] = $total_sell;
        $dash_data['commission'] = $commission;
        $dash_data['delivery_commission'] = $delivery_commission;
        $dash_data['label'] = $label;
        return $dash_data;
    }
}
