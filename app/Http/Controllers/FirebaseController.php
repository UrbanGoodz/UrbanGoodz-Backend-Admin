<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use Illuminate\Http\Request;

class FirebaseController extends Controller
{
    protected $messaging;

    public function __construct()
    {
        $this->messaging = app('firebase.messaging');
    }

    public function subscribeToTopic(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'topic' => 'required|string',
        ]);

        $token = $request->input('token');
        $topic = $request->input('topic');

        // The panels post this from a logged-in page, but the route itself is
        // public, so the topic has to be checked against the caller's own
        // session: without this anyone could subscribe their own device to
        // admin_message and receive every admin notification.
        if (!in_array($topic, $this->topicsAllowedForCurrentSession(), true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            if ($this->messaging) {
                $this->messaging->subscribeToTopic($topic, $token);
                return response()->json(['message' => 'Successfully subscribed to topic'], 200);
            }
            return response()->json(['message' => 'Unauthorized'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /** The exact topic strings the layouts subscribe to, per panel. */
    private function topicsAllowedForCurrentSession(): array
    {
        if (auth('admin')->check()) {
            return ['admin_message', 'admin_safety_alert_notification'];
        }

        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            $storeId = Helpers::get_store_id();

            return $storeId ? ['store_panel_' . $storeId . '_message'] : [];
        }

        return [];
    }
}
