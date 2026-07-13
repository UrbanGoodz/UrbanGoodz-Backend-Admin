<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzAgeVerification;
use App\Models\UrbanGoodzRoutePackage;
use App\Models\UrbanGoodzDedicatedRoute;
use App\Models\Order;
use App\Models\Item;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class UrbanGoodzAgeComplianceController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzAgeVerification::with(['package', 'route', 'order', 'driver', 'reviewer']);

        if ($request->filled('verification_status')) {
            $query->where('verification_status', $request->verification_status);
        }

        if ($request->filled('admin_review_status')) {
            $query->where('admin_review_status', $request->admin_review_status);
        }

        if ($request->filled('needs_review')) {
            $query->needsAdminReview();
        }

        $verifications = $query->latest()->paginate(25);

        $stats = [
            'total' => UrbanGoodzAgeVerification::count(),
            'pending' => UrbanGoodzAgeVerification::where('verification_status', 'pending')->count(),
            'verified' => UrbanGoodzAgeVerification::where('verification_status', 'verified')->count(),
            'failed' => UrbanGoodzAgeVerification::where('verification_status', 'failed')->count(),
            'refused' => UrbanGoodzAgeVerification::where('verification_status', 'refused')->count(),
            'needs_review' => UrbanGoodzAgeVerification::needsAdminReview()->count(),
            'age_restricted_packages' => UrbanGoodzRoutePackage::where('age_restricted', true)->count(),
            'age_restricted_orders' => Order::where('age_restricted_order', true)->count(),
        ];

        return view('admin-views.urban-goodz.age-compliance.index', compact('verifications', 'stats'));
    }

    public function show($id)
    {
        $verification = UrbanGoodzAgeVerification::with([
            'package',
            'package.route',
            'route',
            'order',
            'order.details',
            'driver',
            'reviewer',
        ])->findOrFail($id);

        return view('admin-views.urban-goodz.age-compliance.show', compact('verification'));
    }

    public function packages(Request $request)
    {
        $query = UrbanGoodzRoutePackage::where('age_restricted', true)
            ->orWhere('requires_id_verification', true)
            ->with(['route', 'manifest', 'ageVerifiedByDriver']);

        if ($request->filled('verification_status')) {
            $query->where('age_verification_status', $request->verification_status);
        }

        $packages = $query->latest()->paginate(25);

        return view('admin-views.urban-goodz.age-compliance.packages', compact('packages'));
    }

    public function packageShow($id)
    {
        $package = UrbanGoodzRoutePackage::with([
            'route', 'manifest', 'client', 'ageVerifiedByDriver', 'ageVerifications',
        ])->findOrFail($id);

        return view('admin-views.urban-goodz.age-compliance.package-show', compact('package'));
    }

    public function orders(Request $request)
    {
        $query = Order::where('age_restricted_order', true)
            ->orWhereNotNull('age_verification_status')
            ->with(['customer', 'delivery_man']);

        if ($request->filled('verification_status')) {
            $query->where('age_verification_status', $request->verification_status);
        }

        $orders = $query->latest()->paginate(25);

        return view('admin-views.urban-goodz.age-compliance.orders', compact('orders'));
    }

    public function review(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_age_compliance_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $request->validate([
            'admin_review_status' => 'required|in:pending,reviewed,resolved,escalated',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $verification = UrbanGoodzAgeVerification::findOrFail($id);

        $verification->update([
            'admin_review_status' => $request->admin_review_status,
            'admin_notes' => $request->admin_notes ? ($verification->admin_notes ? $verification->admin_notes . "\n---\n" . $request->admin_notes : $request->admin_notes) : $verification->admin_notes,
            'admin_reviewed_by' => auth('admin')->id(),
            'admin_reviewed_at' => now(),
            'admin_review_required' => in_array($request->admin_review_status, ['escalated']) ? true : ($request->admin_review_status === 'resolved' ? false : $verification->admin_review_required),
        ]);

        Toastr::success(translate('Age verification review updated'));

        return redirect()->route('admin.urban-goodz.age-compliance.show', $id);
    }

    public function items(Request $request)
    {
        $query = Item::where('age_restricted', true)->with(['category', 'store']);

        if ($request->filled('age_restricted_type')) {
            $query->where('age_restricted_type', $request->age_restricted_type);
        }

        $items = $query->latest()->paginate(25);

        return view('admin-views.urban-goodz.age-compliance.items', compact('items'));
    }
}
