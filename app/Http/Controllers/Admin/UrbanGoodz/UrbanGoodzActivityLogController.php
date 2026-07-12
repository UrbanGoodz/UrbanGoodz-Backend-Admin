<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzActivityLog;
use Illuminate\Http\Request;

class UrbanGoodzActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzActivityLog::with('loggable', 'causer');

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }
        if ($request->filled('causer_type')) {
            $query->where('causer_type', $request->causer_type);
        }
        if ($request->filled('loggable_type')) {
            $query->where('loggable_type', $request->loggable_type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('event', 'like', "%{$search}%");
            });
        }
        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->to_date . ' 23:59:59');
        }

        $logs = $query->latest()->paginate(25)->withQueryString();
        $events = UrbanGoodzActivityLog::distinct()->pluck('event')->filter()->values();
        $causerTypes = UrbanGoodzActivityLog::distinct()->pluck('causer_type')->filter()->values();
        $loggableTypes = UrbanGoodzActivityLog::distinct()->pluck('loggable_type')->filter()->values();

        return view('admin-views.urban-goodz.activity-logs.index', compact('logs', 'events', 'causerTypes', 'loggableTypes'));
    }

    public function show($id)
    {
        $log = UrbanGoodzActivityLog::with('loggable', 'causer')->findOrFail($id);

        return view('admin-views.urban-goodz.activity-logs.show', compact('log'));
    }
}
