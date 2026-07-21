<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\AiDispatch;
use App\Models\DeliveryMan;
use App\Models\UrbanGoodzLoadBoardLoad;
use App\Services\UrbanGoodzAiDispatchService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UrbanGoodzDispatchController extends Controller
{
    public function __construct(private UrbanGoodzAiDispatchService $dispatchService) {}

    public function index(Request $request)
    {
        $query = AiDispatch::with(['deliveryMan', 'load', 'route', 'businessClient' => function ($q) {
            $q->select('id', 'company_name');
        }]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('driver_id')) {
            $query->where('delivery_man_id', $request->driver_id);
        }
        if ($request->filled('client_id')) {
            $query->where('business_client_id', $request->client_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('id', $s)
                  ->orWhereHas('load', fn($l) => $l->where('reference_number', 'like', "%{$s}%"))
                  ->orWhereHas('businessClient', fn($c) => $c->where('company_name', 'like', "%{$s}%"));
            });
        }

        $dispatches = $query->latest()->paginate(20);
        $statuses = AiDispatch::$canonicalStatuses;
        $drivers = DeliveryMan::where('active', 1)->where('application_status', 'approved')->get(['id', 'f_name', 'l_name']);

        return view('admin-views.urban-goodz.dispatches.index', compact('dispatches', 'statuses', 'drivers'));
    }

    public function show($id)
    {
        $dispatch = AiDispatch::with(['deliveryMan', 'load', 'route', 'businessClient', 'aiRecommendation'])->findOrFail($id);
        return view('admin-views.urban-goodz.dispatches.show', compact('dispatch'));
    }

    public function create()
    {
        $loads = UrbanGoodzLoadBoardLoad::whereIn('status', ['open', 'pending'])->get();
        $drivers = DeliveryMan::where('active', 1)->where('application_status', 'approved')
            ->get(['id', 'f_name', 'l_name']);
        return view('admin-views.urban-goodz.dispatches.create', compact('loads', 'drivers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'load_id' => 'nullable|exists:urban_goodz_load_board_loads,id',
            'delivery_man_id' => 'required|exists:delivery_men,id',
            'business_client_id' => 'nullable|exists:urban_goodz_business_clients,id',
            'offer_expires_at' => 'nullable|date|after:now',
            'driver_payout_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $dispatch = DB::transaction(function () use ($data) {
            $payload = [
                'delivery_man_id' => $data['delivery_man_id'],
                'status' => AiDispatch::STATUS_APPROVED,
                'created_by_type' => 'admin',
                'created_by_id' => auth('admin')->id(),
                'source_type' => 'admin',
                'source_id' => auth('admin')->id(),
            ];

            if (!empty($data['load_id'])) {
                $payload['load_id'] = $data['load_id'];
                $load = UrbanGoodzLoadBoardLoad::find($data['load_id']);
                if ($load) {
                    $payload['business_client_id'] = $load->business_client_id;
                }
            }
            if (!empty($data['business_client_id'])) {
                $payload['business_client_id'] = $data['business_client_id'];
            }
            if (!empty($data['offer_expires_at'])) {
                $payload['offer_expires_at'] = $data['offer_expires_at'];
            }
            if (!empty($data['driver_payout_amount'])) {
                $payload['driver_payout_amount'] = $data['driver_payout_amount'];
            }
            $payload['metadata'] = ['admin_notes' => $data['notes'] ?? null];

            $dispatch = $this->dispatchService->createAndSend($payload);
            return $dispatch;
        });

        Toastr::success("Dispatch #{$dispatch->id} created and sent to driver.");
        return redirect()->route('admin.urban-goodz.dispatches.index');
    }

    public function approve($id)
    {
        $dispatch = AiDispatch::findOrFail($id);
        $dispatch->approve();
        Toastr::success('Dispatch approved.');
        return back();
    }

    public function sendToDriver($id)
    {
        $dispatch = AiDispatch::findOrFail($id);
        $this->dispatchService->sendToDriver($dispatch);
        Toastr::success('Dispatch sent to driver.');
        return back();
    }

    public function cancel($id, Request $request)
    {
        $dispatch = AiDispatch::findOrFail($id);
        $dispatch->cancelDispatch($request->input('reason'));
        Toastr::success('Dispatch cancelled.');
        return back();
    }

    public function resend($id)
    {
        $dispatch = AiDispatch::findOrFail($id);
        $dispatch->sendToDriver();
        $this->dispatchService->pushToDriver($dispatch);
        Toastr::success('Dispatch resent to driver.');
        return back();
    }

    public function resolveException($id)
    {
        $dispatch = AiDispatch::findOrFail($id);
        $dispatch->resolveException('admin:' . auth('admin')->id());
        Toastr::success('Exception resolved.');
        return back();
    }

    public function settle($id)
    {
        $dispatch = AiDispatch::findOrFail($id);
        $dispatch->settle();
        Toastr::success('Dispatch settled.');
        return back();
    }
}
