<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzWaitlist;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class UrbanGoodzWaitlistController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzWaitlist::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('interest')) {
            $query->where('interest', $request->interest);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('full_name', 'like', "%$s%")
                  ->orWhere('email', 'like', "%$s%")
                  ->orWhere('phone', 'like', "%$s%")
                  ->orWhere('city', 'like', "%$s%");
            });
        }

        $entries = $query->latest()->paginate(25)->appends($request->query());

        $totals = [
            'all' => UrbanGoodzWaitlist::count(),
            'new' => UrbanGoodzWaitlist::where('status', 'new')->count(),
            'contacted' => UrbanGoodzWaitlist::where('status', 'contacted')->count(),
            'onboarded' => UrbanGoodzWaitlist::where('status', 'onboarded')->count(),
        ];

        return view('admin-views.urban-goodz.waitlist.index', compact('entries', 'totals'));
    }

    public function updateStatus($id, Request $request)
    {
        if (!Helpers::module_permission_check('urban_goodz_waitlist_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $entry = UrbanGoodzWaitlist::findOrFail($id);
        $request->validate([
            'status' => ['required', 'in:new,contacted,onboarded,archived'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $entry->update($request->only(['status', 'admin_notes']));

        Toastr::success(translate('Waitlist entry updated.'));
        return back();
    }

    public function destroy($id)
    {
        if (!Helpers::module_permission_check('urban_goodz_waitlist_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        UrbanGoodzWaitlist::findOrFail($id)->delete();

        Toastr::success(translate('Waitlist entry removed.'));
        return back();
    }
}
