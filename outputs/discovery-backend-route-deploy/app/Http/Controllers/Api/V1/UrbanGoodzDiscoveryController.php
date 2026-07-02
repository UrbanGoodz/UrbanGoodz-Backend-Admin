<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UrbanGoodzDiscoveryController extends Controller
{
    public function searchCapture(Request $request)
    {
        $payload = $request->only([
            'search_query',
            'query',
            'module_id',
            'module_name',
            'module_type',
            'category_id',
            'category_name',
            'zone_id',
            'zone_name',
            'city',
            'state',
            'country',
            'user_id',
            'device_platform',
            'timestamp',
            'intent_guess',
            'urgency',
            'notify_me',
            'source_request',
            'request_type',
        ]);

        Log::info('Urban Goodz discovery search captured', [
            'payload' => $payload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Search request captured successfully',
        ]);
    }

    public function entities()
    {
        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }

    public function entity($id)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
            ],
        ]);
    }

    public function entityAction(Request $request, $id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Entity action captured successfully',
            'data' => [
                'id' => $id,
                'action' => $request->input('action'),
            ],
        ]);
    }

    public function opportunities()
    {
        return response()->json([
            'success' => true,
            'data' => [],
        ]);
    }

    public function acceptOpportunity($id)
    {
        return response()->json([
            'success' => true,
            'message' => 'Opportunity accepted successfully',
            'data' => [
                'id' => $id,
            ],
        ]);
    }
}
