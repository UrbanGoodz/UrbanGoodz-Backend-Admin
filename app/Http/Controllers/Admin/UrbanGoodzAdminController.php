<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;
use App\Models\UrbanGoodzBusinessType;
use App\Models\UrbanGoodzCapability;
use App\Models\UrbanGoodzCommunityPost;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzDiscoverySearch;
use App\Models\UrbanGoodzEarnMoneyOpportunity;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzFile;
use App\Models\UrbanGoodzLogisticsJob;
use App\Models\UrbanGoodzMedicalCourierJob;
use App\Models\UrbanGoodzOrderAnywhereCardRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzRentalAsset;
use App\Models\UrbanGoodzRentalBooking;
use App\CentralLogics\Helpers;
use App\Services\OrderAnywhereCardService;
use App\Services\UrbanGoodzPaymentService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UrbanGoodzAdminController extends Controller
{
    public function index()
    {
        if (auth('admin')->user()->role_id != 1 && !Helpers::module_permission_check('urban_goodz_dashboard')) {
            Toastr::error(translate('messages.access_denied'));
            return redirect()->route('admin.dashboard');
        }

        return view('admin-views.urban-goodz.dashboard', [
            'sections' => $this->sections(),
            'counts' => $this->counts(),
        ]);
    }

    public function section(string $section)
    {
        abort_unless(isset($this->sections()[$section]), 404);

        $sectionData = $this->sections()[$section];

        if (in_array($section, ['rentals', 'vehicle-rentals'])) {
            return view('admin-views.urban-goodz.rentals.index', [
                'section' => $sectionData,
                'sectionKey' => $section,
                'businessTypes' => \App\Models\UrbanGoodzBusinessType::whereIn('slug', [
                    'car_rental', 'vehicle_rental', 'equipment_rental', 'rental_provider',
                ])->with('capabilities')->get(),
            ]);
        }

        return view('admin-views.urban-goodz.section', [
            'section' => $sectionData,
            'sectionKey' => $section,
        ]);
    }

    public function orderAnywhere()
    {
        $requests = OrderAnywhereRequest::latest()->paginate(25);

        return view('admin-views.urban-goodz.order-anywhere.index', [
            'requests' => $requests,
            'statuses' => OrderAnywhereRequest::STATUSES,
            'totalRequests' => OrderAnywhereRequest::count(),
            'pendingReview' => OrderAnywhereRequest::where('status', 'pending_review')->count(),
            'activeRequests' => OrderAnywhereRequest::whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count(),
        ]);
    }

    public function orderAnywhereShow($id)
    {
        $record = OrderAnywhereRequest::with(['ledgers.splits', 'paymentSplits'])->findOrFail($id);

        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $id)->latest()->first();
        $issuingMode = config('urban_goodz_payments.issuing.mode', 'sandbox');
        $issuingProvider = config('urban_goodz_payments.issuing.provider', 'manual');

        return view('admin-views.urban-goodz.order-anywhere.show', [
            'request' => $record,
            'statuses' => OrderAnywhereRequest::STATUSES,
            'ledgers' => $record->ledgers()->latest()->get(),
            'splits' => $record->paymentSplits()->latest()->get(),
            'paymentMode' => OrderAnywhereRequest::paymentMode(),
            'liveMaxAmount' => OrderAnywhereRequest::liveMaxAmount(),
            'cardRequest' => $cardRequest,
            'issuingMode' => $issuingMode,
            'issuingProvider' => $issuingProvider,
        ]);
    }

    public function orderAnywhereStatus($id, Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(OrderAnywhereRequest::STATUSES)],
        ]);

        $record = OrderAnywhereRequest::findOrFail($id);
        $record->reviewed_by = auth('admin')->id();
        $record->reviewed_at = now();
        $record->save();
        $record->transitionTo($data['status']);

        return back()->with('success', translate('Order Anywhere status updated successfully.'));
    }

    public function orderAnywhereNotes($id, Request $request)
    {
        $data = $request->validate([
            'admin_notes' => ['nullable', 'string'],
        ]);

        $record = OrderAnywhereRequest::findOrFail($id);
        $record->admin_notes = $data['admin_notes'] ?? null;
        $record->reviewed_by = auth('admin')->id();
        $record->reviewed_at = now();
        $record->save();

        return back()->with('success', translate('Order Anywhere notes updated successfully.'));
    }

    public function orderAnywhereAssign($id, Request $request)
    {
        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer'],
            'assigned_delivery_man_id' => ['nullable', 'integer'],
        ]);

        $record = OrderAnywhereRequest::findOrFail($id);
        $oldStatus = $record->status;
        $record->vendor_id = $data['vendor_id'] ?? $record->vendor_id;
        $record->assigned_delivery_man_id = $data['assigned_delivery_man_id'] ?? $record->assigned_delivery_man_id;

        if (! empty($data['vendor_id']) && $record->isValidTransition($record->status, 'vendor_assigned')) {
            $record->status = 'vendor_assigned';
            $record->vendor_status = 'assigned';
        }

        if (! empty($data['assigned_delivery_man_id']) && $record->isValidTransition($record->status, 'approved')) {
            $record->status = 'approved';
            $record->driver_status = 'assigned';
            $record->assigned_at = now();
        }

        $record->reviewed_by = auth('admin')->id();
        $record->reviewed_at = now();
        $record->save();

        if ($oldStatus !== $record->status) {
            $record->logStatusTransition($oldStatus, $record->status, 'Assignment updated');
        }

        return back()->with('success', translate('Order Anywhere assignment updated successfully.'));
    }

    public function orderAnywhereQuote($id, Request $request, UrbanGoodzPaymentService $payments)
    {
        $data = $request->validate([
            'quote_amount' => ['required', 'numeric', 'min:0.01'],
            'final_amount' => ['nullable', 'numeric', 'min:0.01'],
            'quote_reference' => ['nullable', 'string', 'max:255'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $payments->quoteOrderAnywhere(OrderAnywhereRequest::findOrFail($id), $data);

        return back()->with('success', translate('Order Anywhere quote created successfully.'));
    }

    public function orderAnywhereCapture($id, Request $request, UrbanGoodzPaymentService $payments)
    {
        if (!Helpers::module_permission_check('urban_goodz_order_anywhere_capture_payment')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $data = $request->validate([
            'captured_amount' => ['nullable', 'numeric', 'min:0.01'],
            'final_amount' => ['nullable', 'numeric', 'min:0.01'],
            'payment_method' => ['nullable', 'string', 'max:255'],
            'capture_reference' => ['nullable', 'string', 'max:255'],
            'platform_fee' => ['nullable', 'numeric', 'min:0'],
            'vendor_amount' => ['nullable', 'numeric', 'min:0'],
            'driver_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $payments->captureOrderAnywhere(OrderAnywhereRequest::findOrFail($id), $data);

        return back()->with('success', translate('Order Anywhere payment captured and split successfully.'));
    }

    public function orderAnywhereRefund($id, Request $request, UrbanGoodzPaymentService $payments)
    {
        if (!Helpers::module_permission_check('urban_goodz_order_anywhere_refund')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:0.01'],
            'refund_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
        ]);

        $payments->refundOrderAnywhere(OrderAnywhereRequest::findOrFail($id), $data);

        return back()->with('success', translate('Order Anywhere refund ledger created successfully.'));
    }

    public function orderAnywherePaymentLink($id, Request $request, UrbanGoodzPaymentService $payments)
    {
        if (!Helpers::module_permission_check('urban_goodz_order_anywhere_view')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $record = OrderAnywhereRequest::findOrFail($id);

        $result = $payments->createPaymentLink($record, [
            'amount' => $request->input('amount'),
            'description' => $request->input('description'),
        ]);

        $mode = OrderAnywhereRequest::paymentMode();
        $warning = $mode === 'live_controlled' ? ' [LIVE PAYMENT]' : '';

        return back()->with('success', translate("Order Anywhere payment link created successfully{$warning}."));
    }

    // ─── Driver Card Request Actions ─────────────────────────────────────

    public function orderAnywhereRequestCard($id, Request $request, OrderAnywhereCardService $cardService)
    {
        $data = $request->validate([
            'spending_limit' => ['nullable', 'numeric', 'min:0.01'],
            'card_type' => ['nullable', 'string', Rule::in(['virtual', 'physical'])],
            'single_use' => ['nullable', 'boolean'],
            'expiry_minutes' => ['nullable', 'integer', 'min:15', 'max:1440'],
            'allowed_merchant' => ['nullable', 'string', 'max:255'],
            'allowed_mccs' => ['nullable', 'array'],
        ]);

        $record = $cardService->createCardRequest(OrderAnywhereRequest::findOrFail($id), $data);

        return back()->with('success', translate("Driver card requested successfully. Status: {$record->statusLabel()}."));
    }

    public function orderAnywhereFreezeCard($id, OrderAnywhereCardService $cardService)
    {
        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $id)
            ->whereIn('card_status', ['issued', 'active', 'authorized'])
            ->firstOrFail();

        $cardService->freezeCard($cardRequest);

        return back()->with('success', translate('Driver card frozen successfully.'));
    }

    public function orderAnywhereCancelCard($id, OrderAnywhereCardService $cardService)
    {
        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $id)
            ->whereNotIn('card_status', ['cancelled', 'used', 'reconciled'])
            ->firstOrFail();

        $cardService->cancelCard($cardRequest);

        return back()->with('success', translate('Driver card cancelled successfully.'));
    }

    public function orderAnywhereReconcileCard($id, Request $request, OrderAnywhereCardService $cardService)
    {
        $data = $request->validate([
            'captured_amount' => ['nullable', 'numeric', 'min:0'],
            'refunded_amount' => ['nullable', 'numeric', 'min:0'],
            'merchant_name' => ['nullable', 'string', 'max:255'],
            'receipt_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $cardRequest = UrbanGoodzOrderAnywhereCardRequest::where('order_anywhere_request_id', $id)
            ->whereIn('card_status', ['used', 'frozen'])
            ->firstOrFail();

        $cardService->reconcileCard($cardRequest, $data);

        return back()->with('success', translate('Driver card reconciled successfully.'));
    }

    public function payments(UrbanGoodzPaymentService $payments)
    {
        return view('admin-views.urban-goodz.payments.index', [
            'ledgers' => UrbanGoodzPaymentLedger::with('splits')->latest()->paginate(50),
            'readiness' => $payments->readiness(),
        ]);
    }

    public function paymentDetail(string $module)
    {
        $payments = app(UrbanGoodzPaymentService::class);
        $readiness = $payments->readiness();
        $featureKey = str_replace('-', '_', $module);

        if (isset($readiness[$featureKey]) && $readiness[$featureKey] === 'no_payment_needed') {
            $section = match ($module) {
                'community-marketplace' => 'community',
                default => $module,
            };
            return redirect()->route('admin.urban-goodz.modules.index', ['section' => $section]);
        }

        $moduleMap = [
            'order-anywhere' => ['label' => 'Order Anywhere', 'feature' => 'order_anywhere', 'table' => 'order_anywhere_requests', 'route' => 'admin.urban-goodz.order-anywhere.index'],
            'fashion-fit' => ['label' => 'Fashion Fit', 'feature' => 'fashion_fit', 'table' => 'urban_goodz_measurement_requests', 'route' => 'admin.urban-goodz.fashion-fit.index'],
            'earn-money' => ['label' => 'Earn Money', 'feature' => 'earn_money', 'table' => 'urban_goodz_earn_money_opportunities', 'route' => 'admin.urban-goodz.modules.index', 'route_params' => ['section' => 'earn-money']],
            'logistics' => ['label' => 'Logistics', 'feature' => 'logistics', 'table' => 'urban_goodz_logistics_jobs', 'route' => 'admin.urban-goodz.modules.index', 'route_params' => ['section' => 'logistics']],
            'load-board' => ['label' => 'Load Board', 'feature' => 'load_board', 'table' => 'urban_goodz_logistics_jobs', 'route' => 'admin.urban-goodz.modules.index', 'route_params' => ['section' => 'logistics']],
            'medical-courier' => ['label' => 'Medical Courier', 'feature' => 'medical_courier', 'table' => 'urban_goodz_medical_courier_jobs', 'route' => 'admin.urban-goodz.modules.index', 'route_params' => ['section' => 'medical-courier']],
            'book-anything' => ['label' => 'Book Anything', 'feature' => 'book_anything', 'table' => 'urban_goodz_service_requests', 'route' => 'admin.urban-goodz.modules.index', 'route_params' => ['section' => 'book-anything']],
            'rentals' => ['label' => 'Rentals', 'feature' => 'rentals', 'table' => 'urban_goodz_rental_bookings', 'route' => 'admin.urban-goodz.rentals.dashboard'],
            'events' => ['label' => 'Events', 'feature' => 'events', 'table' => 'urban_goodz_events', 'route' => 'admin.urban-goodz.modules.index', 'route_params' => ['section' => 'events']],
            'creator-commerce' => ['label' => 'Creator Commerce', 'feature' => 'creator_commerce', 'table' => 'urban_goodz_creator_earnings', 'route' => 'admin.urban-goodz.creator.dashboard'],
        ];

        $info = $moduleMap[$module] ?? abort(404);
        $feature = $info['feature'];

        $adminPageUrl = isset($info['route_params'])
            ? route($info['route'], $info['route_params'])
            : route($info['route']);

        $totalRevenue = Schema::hasTable('urban_goodz_payment_ledgers')
            ? UrbanGoodzPaymentLedger::where('feature', $feature)->where('event_type', 'capture')->sum('amount') : 0;
        $totalRefunds = Schema::hasTable('urban_goodz_payment_ledgers')
            ? UrbanGoodzPaymentLedger::where('feature', $feature)->where('event_type', 'refund')->sum('amount') : 0;
        $pendingPayouts = Schema::hasTable('urban_goodz_payment_ledgers')
            ? UrbanGoodzPaymentLedger::where('feature', $feature)->where('payment_status', 'pending')->count() : 0;
        $ledgerCount = Schema::hasTable('urban_goodz_payment_ledgers')
            ? UrbanGoodzPaymentLedger::where('feature', $feature)->count() : 0;
        $recentLedgers = Schema::hasTable('urban_goodz_payment_ledgers')
            ? UrbanGoodzPaymentLedger::where('feature', $feature)->with('splits')->latest()->take(20)->get() : collect();

        $moduleReadiness = $readiness[$feature] ?? 'payment_pending';

        return view('admin-views.urban-goodz.payments.detail', compact('info', 'module', 'totalRevenue', 'totalRefunds', 'pendingPayouts', 'ledgerCount', 'recentLedgers', 'moduleReadiness', 'adminPageUrl'));
    }

    public function fileLibrary(Request $request)
    {
        $query = UrbanGoodzFile::query();

        if ($request->filled('file_category')) {
            $query->where('file_category', $request->file_category);
        }

        if ($request->filled('owner_type')) {
            $query->where('owner_type', $request->owner_type);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('original_name', 'like', '%' . $request->search . '%')
                    ->orWhere('stored_path', 'like', '%' . $request->search . '%');
            });
        }

        $files = $query->latest()->paginate(50)->appends($request->query());

        return view('admin-views.urban-goodz.files.index', [
            'files' => $files,
            'categories' => UrbanGoodzFile::select('file_category')->distinct()->pluck('file_category'),
            'ownerTypes' => UrbanGoodzFile::select('owner_type')->distinct()->pluck('owner_type'),
        ]);
    }

    private function counts(): array
    {
        return [
            'order_anywhere' => Schema::hasTable('order_anywhere_requests') ? OrderAnywhereRequest::count() : 0,
            'fashion_fit' => Schema::hasTable('urban_goodz_measurement_requests') ? MeasurementRequest::count() : 0,
            'payments' => Schema::hasTable('urban_goodz_payment_ledgers') ? UrbanGoodzPaymentLedger::count() : 0,
            'rental_assets' => Schema::hasTable('urban_goodz_rental_assets') ? UrbanGoodzRentalAsset::count() : 0,
            'rental_bookings' => Schema::hasTable('urban_goodz_rental_bookings') ? UrbanGoodzRentalBooking::count() : 0,
            'ai_conversations' => Schema::hasTable('urban_goodz_ai_conversations') ? UrbanGoodzAIConversation::count() : 0,
            'ai_intents' => Schema::hasTable('urban_goodz_ai_intents') ? UrbanGoodzAIIntent::count() : 0,
            'business_types' => Schema::hasTable('urban_goodz_business_types') ? UrbanGoodzBusinessType::count() : 0,
            'capabilities' => Schema::hasTable('urban_goodz_capabilities') ? UrbanGoodzCapability::count() : 0,
            'logistics_jobs' => Schema::hasTable('urban_goodz_logistics_jobs') ? UrbanGoodzLogisticsJob::count() : 0,
            'medical_courier_jobs' => Schema::hasTable('urban_goodz_medical_courier_jobs') ? UrbanGoodzMedicalCourierJob::count() : 0,
            'events' => Schema::hasTable('urban_goodz_events') ? UrbanGoodzEvent::count() : 0,
            'earn_opportunities' => Schema::hasTable('urban_goodz_earn_money_opportunities') ? UrbanGoodzEarnMoneyOpportunity::count() : 0,
            'creator_applications' => Schema::hasTable('urban_goodz_creator_applications') ? UrbanGoodzCreatorApplication::count() : 0,
            'community_posts' => Schema::hasTable('urban_goodz_community_posts') ? UrbanGoodzCommunityPost::count() : 0,
            'discovery_searches' => Schema::hasTable('urban_goodz_discovery_searches') ? UrbanGoodzDiscoverySearch::count() : 0,
            'business_clients' => Schema::hasTable('urban_goodz_business_clients') ? \App\Models\UrbanGoodzBusinessClient::count() : 0,
        ];
    }

    private function sections(): array
    {
        return [
            'order-anywhere' => [
                'title' => 'Order Anywhere',
                'url' => route('admin.urban-goodz.order-anywhere.index'),
                'status' => 'Live',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'List, detail, status, notes, assign driver, quote, capture payment, refund.',
            ],
            'payments' => [
                'title' => 'Payment Center',
                'url' => route('admin.urban-goodz.payments.index'),
                'status' => 'Live',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Ledger, splits, refunds, readiness, financial settings.',
            ],
            'fashion-fit' => [
                'title' => 'Fashion Fit',
                'url' => route('admin.urban-goodz.fashion-fit.index'),
                'status' => 'Live',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Measurement requests, stylist requests, measurement profiles, fashion files.',
            ],
            'rentals' => [
                'title' => 'Rentals',
                'url' => route('admin.urban-goodz.section', 'rentals'),
                'status' => 'Live',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Car, vehicle, equipment rental assets, bookings, deposits, verification, inspections, damage reports.',
            ],
            'vehicle-rentals' => [
                'title' => 'Vehicle Rentals',
                'url' => route('admin.urban-goodz.section', 'vehicle-rentals'),
                'status' => 'Live',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Vehicle-specific rental management, availability, rates, pickup/return, damage reports.',
            ],
            'ai-concierge' => [
                'title' => 'AI Concierge',
                'url' => route('admin.urban-goodz.ai-concierge.intents'),
                'status' => 'Live',
                'revenue' => false,
                'reportable' => true,
                'admin_workflow' => 'Conversations, intents, AI settings, usage analytics, AI copilot.',
            ],
            'business-types' => [
                'title' => 'Business Types',
                'url' => route('admin.urban-goodz.business-types.index'),
                'status' => 'Live',
                'revenue' => false,
                'reportable' => false,
                'admin_workflow' => 'Manage business type definitions, module mapping, capabilities assignment.',
            ],
            'capabilities' => [
                'title' => 'Capabilities',
                'url' => route('admin.urban-goodz.capabilities.index'),
                'status' => 'Live',
                'revenue' => false,
                'reportable' => false,
                'admin_workflow' => 'Manage capability definitions for business types.',
            ],
            'files' => [
                'title' => 'File Library',
                'url' => route('admin.urban-goodz.files.index'),
                'status' => 'Live',
                'revenue' => false,
                'reportable' => false,
                'admin_workflow' => 'Upload, categorize, and manage files across Urban Goodz modules.',
            ],
            'earn-money' => [
                'title' => 'Earn Money',
                'url' => route('admin.urban-goodz.section', 'earn-money'),
                'status' => 'DB-Backed',
                'table' => 'urban_goodz_earn_money_opportunities',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Driver/partner opportunities, applications, partner management.',
            ],
            'logistics' => [
                'title' => 'Logistics',
                'url' => route('admin.urban-goodz.section', 'logistics'),
                'status' => 'Live',
                'table' => 'urban_goodz_logistics_jobs',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Logistics jobs, load board, dispatching.',
            ],
            'medical-courier' => [
                'title' => 'Medical Courier',
                'url' => route('admin.urban-goodz.section', 'medical-courier'),
                'status' => 'Live',
                'table' => 'urban_goodz_medical_courier_jobs',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Courier jobs, custody logs, chain-of-custody tracking.',
            ],
            'events' => [
                'title' => 'Events',
                'url' => route('admin.urban-goodz.section', 'events'),
                'status' => 'Live',
                'table' => 'urban_goodz_events',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Event management, ticketing, promotions.',
            ],
            'creators' => [
                'title' => 'Creator Commerce',
                'url' => route('admin.urban-goodz.creator.dashboard'),
                'status' => 'Live',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Applications, profiles, campaigns, shoppable content, earnings, business leads, event promotions, AI tools, reports.',
            ],
            'community' => [
                'title' => 'Community Marketplace',
                'url' => route('admin.urban-goodz.section', 'community'),
                'status' => 'DB-Backed',
                'table' => 'urban_goodz_community_posts',
                'revenue' => false,
                'reportable' => false,
                'admin_workflow' => 'Community posts, comments, marketplace listings.',
            ],
            'discovery' => [
                'title' => 'Business Discovery',
                'url' => route('admin.urban-goodz.section', 'discovery'),
                'status' => 'Live',
                'table' => 'urban_goodz_discovery_searches',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Search captures, business leads, demand signals. API: search-capture, entities, opportunities. Needs admin management UI.',
            ],
            'business-clients' => [
                'title' => 'Business Clients',
                'url' => route('admin.urban-goodz.business-clients.index'),
                'status' => 'Live',
                'table' => 'urban_goodz_business_clients',
                'revenue' => true,
                'reportable' => true,
                'admin_workflow' => 'Company registration, employee management, location management, document verification, job creation, quoting, driver assignment, invoicing.',
            ],
        ];
    }
}
