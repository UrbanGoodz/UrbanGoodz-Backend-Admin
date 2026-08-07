<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzWaitlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class UrbanGoodzWaitlistController extends Controller
{
    // Website waitlist / lead capture. The marketing site is a static build
    // with no server runtime, so signups post straight here. Public by design
    // (pre-signup), throttled at the route, and honeypot-guarded below.
    public function store(Request $request)
    {
        // Honeypot: bots fill the hidden field. Accept quietly, store nothing.
        if ($request->filled('company')) {
            return response()->json(['message' => 'You are on the waitlist.'], 200);
        }

        $validator = Validator::make($request->all(), [
            'full_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:191'],
            'interest' => ['required', 'string', 'in:app,business,driver,samaritan,other'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:191'],
            'page' => ['nullable', 'string', 'max:191'],
            'consent' => ['required'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 422);
        }

        $data = $validator->validated();
        $data['consent'] = in_array($data['consent'], [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
        $data['user_agent'] = mb_substr((string) $request->userAgent(), 0, 191);
        $data['ip_address'] = $request->ip();

        UrbanGoodzWaitlist::create($data);

        return response()->json(['message' => 'You are on the waitlist.'], 200);
    }
}
