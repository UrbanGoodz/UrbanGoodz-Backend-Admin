<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzEvent;
use Illuminate\Support\Facades\Auth;

class UrbanGoodzEventAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzEvent::with('sourcingRecord');
        
        if ($request->has('status')) {
            $query->where('status', $request->status); // Adjust to approval_state if needed
        }
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }
        if ($request->has('city')) {
            $query->where('city', $request->city);
        }
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }
        
        return response()->json($query->paginate(15));
    }

    public function create()
    {
        return view('admin.events.create'); // Assuming view exists
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'source' => 'required|string',
        ]);
        
        $validated['organiser_user_id'] = Auth::id();
        $validated['organiser_type'] = 'admin';
        
        $event = UrbanGoodzEvent::create($validated);
        
        $event->sourcingRecord()->create([
            'source' => $request->source,
            'approval_state' => 'approved',
            'visibility_state' => 'visible',
            'admin_id' => Auth::id(),
        ]);
        
        return response()->json(['message' => 'Event created', 'data' => $event], 201);
    }

    public function show($id)
    {
        $event = UrbanGoodzEvent::with('sourcingRecord')->findOrFail($id);
        return response()->json($event);
    }

    public function update(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        $event->update($request->all());
        return response()->json(['message' => 'Event updated', 'data' => $event]);
    }

    public function updateStatus(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        $event->update(['approval_state' => $request->status]);
        return response()->json(['message' => 'Status updated']);
    }

    public function toggleFeatured($id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        $event->update(['featured_at' => $event->featured_at ? null : now()]);
        return response()->json(['message' => 'Featured toggled']);
    }

    public function expiredEvents(Request $request)
    {
        $query = UrbanGoodzEvent::whereNotNull('expires_at')->where('expires_at', '<', now());
        return response()->json($query->paginate(15));
    }

    public function duplicates(Request $request)
    {
        // Simple duplicates logic based on similar title/date
        return response()->json(['data' => []]);
    }
}
