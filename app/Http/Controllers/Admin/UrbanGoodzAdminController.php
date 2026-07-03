<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeasurementRequest;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UrbanGoodzAdminController extends Controller
{
    public function index()
    {
        return view('admin-views.urban-goodz.dashboard', [
            'sections' => $this->sections(),
            'counts' => $this->counts(),
        ]);
    }

    public function section(string $section)
    {
        abort_unless(isset($this->sections()[$section]), 404);

        return view('admin-views.urban-goodz.section', [
            'section' => $this->sections()[$section],
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

        return view('admin-views.urban-goodz.order-anywhere.show', [
            'request' => $record,
            'statuses' => OrderAnywhereRequest::STATUSES,
            'ledgers' => $record->ledgers()->latest()->get(),
            'splits' => $record->paymentSplits()->latest()->get(),
        ]);
    }

    public function orderAnywhereStatus($id, Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(OrderAnywhereRequest::STATUSES)],
        ]);

        $record = OrderAnywhereRequest::findOrFail($id);
        $record->status = $data['status'];
        $record->reviewed_by = auth('admin')->id();
        $record->reviewed_at = now();
        $record->save();

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
        $record->vendor_id = $data['vendor_id'] ?? $record->vendor_id;
        $record->assigned_delivery_man_id = $data['assigned_delivery_man_id'] ?? $record->assigned_delivery_man_id;

        if (! empty($data['vendor_id']) && $record->status === 'pending_review') {
            $record->status = 'vendor_assigned';
            $record->vendor_status = 'assigned';
        }

        $record->reviewed_by = auth('admin')->id();
        $record->reviewed_at = now();
        $record->save();

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
        $data = $request->validate([
            'refund_amount' => ['required', 'numeric', 'min:0.01'],
            'refund_reference' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string'],
        ]);

        $payments->refundOrderAnywhere(OrderAnywhereRequest::findOrFail($id), $data);

        return back()->with('success', translate('Order Anywhere refund ledger created successfully.'));
    }

    public function payments(UrbanGoodzPaymentService $payments)
    {
        return view('admin-views.urban-goodz.payments.index', [
            'ledgers' => UrbanGoodzPaymentLedger::with('splits')->latest()->paginate(50),
            'readiness' => $payments->readiness(),
        ]);
    }

    private function counts(): array
    {
        return [
            'order_anywhere' => Schema::hasTable('order_anywhere_requests') ? OrderAnywhereRequest::count() : 0,
            'fashion_fit' => Schema::hasTable('urban_goodz_measurement_requests') ? MeasurementRequest::count() : 0,
        ];
    }

    private function sections(): array
    {
        return [
            'order-anywhere' => [
                'title' => 'Order Anywhere Requests',
                'url' => route('admin.urban-goodz.order-anywhere.index'),
                'status' => 'DB-backed',
                'table' => 'order_anywhere_requests',
                'customer_api' => 'POST /api/v1/order-anywhere/requests',
                'admin_workflow' => 'List, detail, status, notes, driver assignment fields',
                'notes' => 'Converted from temporary JSON to Eloquent and SQL table.',
            ],
            'payments' => [
                'title' => 'Payment Center',
                'url' => route('admin.urban-goodz.payments.index'),
                'status' => 'Payment-ready',
                'table' => 'urban_goodz_payment_ledgers, urban_goodz_payment_splits',
                'customer_api' => 'Feature-specific payment actions',
                'admin_workflow' => 'Ledger, splits, refunds, readiness',
                'notes' => 'Reusable Urban Goodz payment accounting layer.',
            ],
            'fashion-fit' => [
                'title' => 'Fashion Fit',
                'url' => route('admin.urban-goodz.fashion-fit.index'),
                'status' => 'Partially DB-backed',
                'table' => 'urban_goodz_measurement_requests',
                'customer_api' => 'GET/POST /api/v1/urban-goodz/fashion/* and /api/v1/urban-goodz/fashion/measurements/*',
                'admin_workflow' => 'Existing measurement/stylist request list; detail route needs view completion.',
                'notes' => 'Measurement requests are real DB records. Separate profile/photo-guide/bid tables are not present.',
            ],
            'earn-money' => $this->missingSection('earn-money', 'Earn Money', 'urban_goodz_earn_money_opportunities, urban_goodz_earn_money_applications', 'GET /api/v1/urban-goodz/earn-money/opportunities'),
            'logistics' => $this->missingSection('logistics', 'Logistics Jobs', 'urban_goodz_logistics_jobs', 'GET /api/v1/urban-goodz/logistics/jobs'),
            'load-board' => $this->missingSection('load-board', 'Load Board', 'urban_goodz_load_board_loads', 'GET /api/v1/urban-goodz/load-board/loads'),
            'medical-courier' => $this->missingSection('medical-courier', 'Medical Courier', 'urban_goodz_medical_courier_jobs, urban_goodz_medical_courier_custody_logs', 'GET /api/v1/urban-goodz/medical-courier/jobs'),
            'book-anything' => $this->missingSection('book-anything', 'Book Anything', 'urban_goodz_service_requests, urban_goodz_service_providers, urban_goodz_appointments', 'GET /api/v1/urban-goodz/book-anything/records'),
            'rentals' => [
                'title' => 'Rentals',
                'url' => route('admin.urban-goodz.section', 'rentals'),
                'status' => 'Existing 6amMart rental module plus Urban Goodz admin gap',
                'table' => 'Rental module tables; requested urban_goodz_rentals tables not present in this repo.',
                'customer_api' => 'Existing rental module APIs/routes where enabled',
                'admin_workflow' => 'Use existing module management now; Urban Goodz rental request tables still missing.',
                'notes' => 'Do not duplicate rental module blindly.',
            ],
            'events' => $this->missingSection('events', 'Events', 'urban_goodz_events and related event opportunity tables', 'GET /api/v1/urban-goodz/events'),
            'creators' => [
                'title' => 'Creator Commerce / Reels',
                'url' => route('admin.urban-goodz.section', 'creators'),
                'status' => 'Mixed: Reels module exists; creator commerce tester JSON exists',
                'table' => 'Reels module tables; creator commerce SQL tables not present',
                'customer_api' => 'Modules/ReelsModule API and /api/v1/urban-goodz/creator-commerce/*',
                'admin_workflow' => 'Reels admin exists under /admin/reels when module is enabled; creator applications still need SQL workflow.',
                'notes' => 'Creator tester JSON should be migrated in a later sprint like Order Anywhere.',
            ],
            'community' => $this->missingSection('community', 'Community Marketplace', 'urban_goodz_community_posts, urban_goodz_community_comments, urban_goodz_community_marketplace_items', 'Not found'),
            'discovery' => [
                'title' => 'Discovery / Search Capture',
                'url' => route('admin.urban-goodz.section', 'discovery'),
                'status' => 'API exists, DB missing',
                'table' => 'urban_goodz_discovery_* tables not present',
                'customer_api' => 'POST /api/v1/urban-goodz/discovery/search-capture',
                'admin_workflow' => 'Needs DB persistence before admin can show captured searches.',
                'notes' => 'Current controller logs searches only.',
            ],
            'ask' => $this->missingSection('ask', 'Ask Urban Goodz', 'urban_goodz_ask_requests', 'Not found'),
            'plus' => $this->missingSection('plus', 'Urban Goodz+', 'urban_goodz_plus_memberships, urban_goodz_plus_requests, urban_goodz_plus_benefits', 'Not found'),
            'spotlight' => $this->missingSection('spotlight', 'Black-Owned Spotlight', 'urban_goodz_spotlight_businesses, urban_goodz_spotlight_requests', 'Not found'),
        ];
    }

    private function missingSection(string $key, string $title, string $table, string $api): array
    {
        return [
            'title' => $title,
            'url' => route('admin.urban-goodz.section', $key),
            'status' => 'Admin/backend DB missing',
            'table' => $table,
            'customer_api' => $api,
            'admin_workflow' => 'Direct admin status page added; real list/detail workflow requires SQL table and persistence controller.',
            'notes' => 'Checked in this sprint and not marked fully integrated.',
        ];
    }
}
