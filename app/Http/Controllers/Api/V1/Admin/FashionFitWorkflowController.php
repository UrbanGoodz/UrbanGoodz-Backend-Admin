<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FashionFitAnalysis;
use App\Models\FashionFitAuditEvent;
use App\Models\FashionFitProfile;
use App\Models\FashionFitProviderProfile;
use App\Models\FashionFitRequest;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class FashionFitWorkflowController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'profiles' => FashionFitProfile::count(),
            'analyses' => FashionFitAnalysis::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'requests' => FashionFitRequest::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'provider_configured' => filled(config('fashion_fit_ai.endpoint')) && filled(config('fashion_fit_ai.api_key')),
            'model' => config('fashion_fit_ai.model'),
            'model_version' => config('fashion_fit_ai.model_version'),
        ]);
    }

    public function requests(Request $request)
    {
        return response()->json(FashionFitRequest::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()->paginate(50));
    }

    public function audits(Request $request)
    {
        return response()->json(FashionFitAuditEvent::query()
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->event))
            ->latest('id')->paginate(100));
    }

    public function providerStatus(Request $request, Vendor $vendor)
    {
        $status = $request->validate(['status' => ['required', Rule::in(['approved', 'suspended'])]])['status'];
        $profile = FashionFitProviderProfile::updateOrCreate(['vendor_id' => $vendor->id], [
            'status' => $status,
            'approved_at' => $status === 'approved' ? now() : null,
            'approved_by' => auth('admin')->id(),
        ]);
        FashionFitAuditEvent::create([
            'actor_type' => 'admin', 'actor_id' => auth('admin')->id(),
            'event' => 'provider_'.$status, 'auditable_type' => Vendor::class,
            'auditable_id' => $vendor->id, 'metadata' => ['scope' => 'fashion_fit'], 'created_at' => now(),
        ]);
        return response()->json(['data' => $profile]);
    }
}
