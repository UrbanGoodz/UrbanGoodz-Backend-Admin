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
        $record->vendor_status = $data['vendor_status'];
        $record->vendor_notes = $data['vendor_notes'] ?? $record->vendor_notes;
        $record->vendor_quote_amount = $data['vendor_quote_amount'] ?? $record->vendor_quote_amount;

        if ($data['vendor_status'] === 'accepted') {
            $record->status = 'vendor_accepted';
        } elseif ($data['vendor_status'] === 'declined') {
            $record->status = 'quote_needed';
        } elseif ($data['vendor_status'] === 'ready_for_pickup') {
            $record->status = 'shopping';
        }

        $record->save();

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
