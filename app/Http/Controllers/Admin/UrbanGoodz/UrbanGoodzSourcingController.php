<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UrbanGoodzSourcingRecord;
use App\Models\UrbanGoodzSourcingLead;

class UrbanGoodzSourcingController extends Controller
{
    public function dashboard()
    {
        $queueCounts = [
            'pending_validation' => UrbanGoodzSourcingRecord::where('validation_state', 'pending')->count(),
            'suspected_duplicates' => UrbanGoodzSourcingRecord::where('duplicate_state', 'suspected')->count(),
            'pending_approval' => UrbanGoodzSourcingRecord::where('approval_state', 'pending')->count(),
            'new_leads' => UrbanGoodzSourcingLead::where('outreach_status', 'new')->count(),
        ];
        
        return response()->json($queueCounts);
    }

    public function leads(Request $request)
    {
        $query = UrbanGoodzSourcingLead::query();
        
        if ($request->has('status')) {
            $query->where('outreach_status', $request->status);
        }
        
        return response()->json($query->paginate(15));
    }

    public function storeLead(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'name' => 'required|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'source' => 'required|string',
        ]);
        
        $lead = UrbanGoodzSourcingLead::create($validated);
        return response()->json(['message' => 'Lead created', 'data' => $lead], 201);
    }

    public function outreachQueue(Request $request)
    {
        $query = UrbanGoodzSourcingLead::whereIn('outreach_status', ['new', 'contacted', 'in_progress']);
        return response()->json($query->paginate(15));
    }

    public function validationQueue(Request $request)
    {
        $query = UrbanGoodzSourcingRecord::with('sourceable')->where('validation_state', 'pending');
        return response()->json($query->paginate(15));
    }

    public function duplicateQueue(Request $request)
    {
        $query = UrbanGoodzSourcingRecord::with('sourceable')->where('duplicate_state', 'suspected');
        return response()->json($query->paginate(15));
    }

    public function moderationQueue(Request $request)
    {
        $query = UrbanGoodzSourcingRecord::with('sourceable')->where('approval_state', 'pending'); // Assuming moderation involves approval
        return response()->json($query->paginate(15));
    }

    public function approvalQueue(Request $request)
    {
        $query = UrbanGoodzSourcingRecord::with('sourceable')->where('approval_state', 'pending');
        return response()->json($query->paginate(15));
    }

    public function updateRecordState(Request $request, $id)
    {
        $record = UrbanGoodzSourcingRecord::findOrFail($id);
        
        $record->update($request->only(['validation_state', 'approval_state', 'visibility_state', 'duplicate_state']));
        
        // Audit log logic here
        
        return response()->json(['message' => 'State updated', 'data' => $record]);
    }

    public function featureRecord(Request $request, $type, $id)
    {
        $modelClass = $type == 'event' ? \App\Models\UrbanGoodzEvent::class : \App\Models\UrbanGoodzCreatorProfile::class;
        $model = $modelClass::findOrFail($id);
        
        $model->update(['featured_at' => $model->featured_at ? null : now()]);
        
        return response()->json(['message' => 'Featured state toggled']);
    }

    public function auditHistory(Request $request)
    {
        // ... return paginated audit logs ...
        return response()->json(['data' => []]);
    }

    public function csvImport()
    {
        return view('admin.sourcing.csv_import'); // Assuming view exists
    }

    public function processCsvImport(Request $request)
    {
        $request->validate(['csv_file' => 'required|file|mimes:csv,txt']);
        
        // ... parse CSV and create models ...
        return response()->json(['message' => 'CSV processed']);
    }
}
