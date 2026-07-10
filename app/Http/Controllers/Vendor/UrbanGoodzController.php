<?php

namespace App\Http\Controllers\Vendor;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentSplit;
use Illuminate\Http\Request;

class UrbanGoodzController extends Controller
{
    public function index()
    {
        $vendorId = Helpers::get_vendor_id();

        return view('vendor-views.urban-goodz.dashboard', [
            'orderAnywhereCount' => OrderAnywhereRequest::where('vendor_id', $vendorId)->count(),
        ]);
    }

    public function orderAnywhere()
    {
        $requests = OrderAnywhereRequest::where('vendor_id', Helpers::get_vendor_id())
            ->latest()
            ->paginate(25);

        return view('vendor-views.urban-goodz.order-anywhere.index', [
            'requests' => $requests,
        ]);
    }

    public function orderAnywhereShow($id)
    {
        $request = OrderAnywhereRequest::where('vendor_id', Helpers::get_vendor_id())->findOrFail($id);

        return view('vendor-views.urban-goodz.order-anywhere.show', [
            'request' => $request,
            'splits' => $request->paymentSplits()
                ->where('recipient_type', 'vendor')
                ->where('recipient_id', Helpers::get_vendor_id())
                ->latest()
                ->get(),
        ]);
    }

    public function orderAnywhereUpdate($id, Request $request)
    {
        $data = $request->validate([
            'vendor_status' => ['required', 'in:accepted,declined,quote_submitted,in_progress,ready_for_pickup,completed'],
            'vendor_notes' => ['nullable', 'string'],
            'vendor_quote_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $record = OrderAnywhereRequest::where('vendor_id', Helpers::get_vendor_id())->findOrFail($id);
        $oldStatus = $record->status;
        $record->vendor_status = $data['vendor_status'];
        $record->vendor_notes = $data['vendor_notes'] ?? $record->vendor_notes;
        $record->vendor_quote_amount = $data['vendor_quote_amount'] ?? $record->vendor_quote_amount;

        $newStatus = match ($data['vendor_status']) {
            'accepted' => 'vendor_accepted',
            'declined' => 'quote_needed',
            'ready_for_pickup' => 'shopping',
            default => $record->status,
        };

        if ($newStatus !== $record->status && $record->isValidTransition($record->status, $newStatus)) {
            $record->status = $newStatus;
        }

        $record->save();

        if ($oldStatus !== $record->status) {
            $record->logStatusTransition($oldStatus, $record->status, 'Vendor update: ' . $data['vendor_status']);
        }

        return back()->with('success', translate('Order Anywhere vendor response updated successfully.'));
    }

    public function section(string $section)
    {
        return view('vendor-views.urban-goodz.section', [
            'title' => str($section)->replace('-', ' ')->title(),
            'section' => $section,
        ]);
    }

    public function payments()
    {
        return view('vendor-views.urban-goodz.payments.index', [
            'splits' => UrbanGoodzPaymentSplit::query()
                ->where('recipient_type', 'vendor')
                ->where('recipient_id', Helpers::get_vendor_id())
                ->latest()
                ->paginate(50),
        ]);
    }
}
