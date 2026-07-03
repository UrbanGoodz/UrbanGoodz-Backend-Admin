<?php

namespace App\Http\Controllers\DeliveryMan;

use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentSplit;
use Illuminate\Http\Request;

class UrbanGoodzController extends Controller
{
    public function index()
    {
        $driverId = auth('delivery_men')->id();

        return view('delivery-man-views.urban-goodz.dashboard', [
            'assignedCount' => OrderAnywhereRequest::where('assigned_delivery_man_id', $driverId)->count(),
        ]);
    }

    public function jobs()
    {
        return $this->orderAnywhere();
    }

    public function orderAnywhere()
    {
        $requests = OrderAnywhereRequest::where('assigned_delivery_man_id', auth('delivery_men')->id())
            ->whereNotIn('status', ['completed', 'cancelled', 'rejected'])
            ->latest()
            ->paginate(25);

        return view('delivery-man-views.urban-goodz.order-anywhere.index', [
            'requests' => $requests,
        ]);
    }

    public function orderAnywhereShow($id)
    {
        $request = OrderAnywhereRequest::where('assigned_delivery_man_id', auth('delivery_men')->id())->findOrFail($id);

        return view('delivery-man-views.urban-goodz.order-anywhere.show', [
            'request' => $request,
            'splits' => $request->paymentSplits()
                ->where('recipient_type', 'driver')
                ->where('recipient_id', auth('delivery_men')->id())
                ->latest()
                ->get(),
        ]);
    }

    public function orderAnywhereStatus($id, Request $request)
    {
        $data = $request->validate([
            'driver_task_status' => ['required', 'in:accepted,picked_up,en_route,delivered,issue_reported'],
            'driver_notes' => ['nullable', 'string'],
        ]);

        $record = OrderAnywhereRequest::where('assigned_delivery_man_id', auth('delivery_men')->id())->findOrFail($id);
        $record->driver_task_status = $data['driver_task_status'];
        $record->driver_notes = $data['driver_notes'] ?? $record->driver_notes;
        $record->status = match ($data['driver_task_status']) {
            'picked_up' => 'picked_up',
            'en_route' => 'out_for_delivery',
            'delivered' => 'completed',
            'issue_reported' => 'reviewing',
            default => 'shopping',
        };
        $record->save();

        return back()->with('success', 'Order Anywhere driver status updated.');
    }

    public function section(string $section)
    {
        return view('delivery-man-views.urban-goodz.section', [
            'title' => str($section)->replace('-', ' ')->title(),
            'section' => $section,
        ]);
    }

    public function payments()
    {
        return view('delivery-man-views.urban-goodz.payments.index', [
            'splits' => UrbanGoodzPaymentSplit::query()
                ->where('recipient_type', 'driver')
                ->where('recipient_id', auth('delivery_men')->id())
                ->latest()
                ->paginate(50),
        ]);
    }
}
