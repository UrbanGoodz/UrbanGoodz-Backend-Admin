<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzEvent;
use App\Models\UrbanGoodzEventSave;
use App\Models\UrbanGoodzEventReminder;
use Illuminate\Support\Facades\Auth;

class EventMarketplaceController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzEvent::published()->visible()->notExpired()->notCancelled();

        if ($request->has('nearby') && $request->has('lat') && $request->has('lng')) {
            $radius = $request->get('radius', 10);
            $query->nearby($request->lat, $request->lng, $radius);
        }

        if ($request->has('today')) {
            $query->today();
        } elseif ($request->has('this_weekend')) {
            $query->thisWeekend();
        } elseif ($request->has('upcoming')) {
            $query->upcoming();
        }

        if ($request->has('free')) {
            $query->free();
        } elseif ($request->has('paid')) {
            $query->paid();
        }

        if ($request->has('category')) {
            $query->category($request->category);
        }

        if ($request->has('city')) {
            $query->city($request->city);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->has('date_from')) {
            $query->where('starts_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('starts_at', '<=', $request->date_to);
        }

        $sort = $request->get('sort', 'date');
        if ($sort == 'distance' && $request->has('lat') && $request->has('lng')) {
            $query->orderBy('distance', 'asc');
        } elseif ($sort == 'popularity') {
            $query->withCount('saves')->orderByDesc('saves_count');
        } else {
            $query->orderBy('starts_at', 'asc');
        }

        return response()->json($query->paginate(15));
    }

    public function show(Request $request, $id)
    {
        $event = UrbanGoodzEvent::with(['venue', 'creatorAppearanceProfiles', 'saves'])
            ->findOrFail($id);
            
        $event->save_count = $event->saves->count();
        
        if (Auth::check()) {
            $event->is_saved = $event->saves()->where('user_id', Auth::id())->exists();
            $event->is_reminded = $event->reminders()->where('user_id', Auth::id())->exists();
        }

        return response()->json($event);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'description' => 'required|string',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'category' => 'nullable|string',
            'city' => 'nullable|string',
            'venue_name' => 'nullable|string',
        ]);
        
        $validated['organiser_user_id'] = Auth::id();
        $validated['organiser_type'] = 'user';
        
        $event = UrbanGoodzEvent::create($validated);
        
        return response()->json(['message' => 'Event created', 'data' => $event], 201);
    }

    public function update(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        
        if ($event->organiser_user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $event->update($request->all());
        
        return response()->json(['message' => 'Event updated', 'data' => $event]);
    }

    public function cancel(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        
        if ($event->organiser_user_id !== Auth::id() && Auth::user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $event->update(['cancelled_at' => now()]);
        
        return response()->json(['message' => 'Event cancelled']);
    }

    public function saveEvent(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        
        UrbanGoodzEventSave::firstOrCreate([
            'user_id' => Auth::id(),
            'event_id' => $event->id,
        ]);
        
        return response()->json(['message' => 'Event saved']);
    }

    public function unsaveEvent(Request $request, $id)
    {
        UrbanGoodzEventSave::where('user_id', Auth::id())
            ->where('event_id', $id)
            ->delete();
            
        return response()->json(['message' => 'Event unsaved']);
    }

    public function shareEvent(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        // Assuming share count column
        // $event->increment('shares_count');
        return response()->json(['message' => 'Event shared']);
    }

    public function setReminder(Request $request, $id)
    {
        $event = UrbanGoodzEvent::findOrFail($id);
        
        $request->validate(['remind_at' => 'required|date']);
        
        UrbanGoodzEventReminder::updateOrCreate(
            ['user_id' => Auth::id(), 'event_id' => $event->id],
            ['remind_at' => $request->remind_at]
        );
        
        return response()->json(['message' => 'Reminder set']);
    }

    public function removeReminder(Request $request, $id)
    {
        UrbanGoodzEventReminder::where('user_id', Auth::id())
            ->where('event_id', $id)
            ->delete();
            
        return response()->json(['message' => 'Reminder removed']);
    }

    public function expressInterest(Request $request, $id)
    {
        // ... record interest ...
        return response()->json(['message' => 'Interest recorded']);
    }

    public function reportEvent(Request $request, $id)
    {
        // ... report event ...
        return response()->json(['message' => 'Event reported']);
    }

    public function savedEvents(Request $request)
    {
        $saves = UrbanGoodzEventSave::where('user_id', Auth::id())
            ->with('event')
            ->paginate(15);
            
        return response()->json($saves);
    }

    public function categories()
    {
        return response()->json([
            'Music', 'Art', 'Food', 'Technology', 'Sports', 'Fashion', 'Networking'
        ]);
    }
}
