<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorNotification;
use Illuminate\Http\Request;

class VendorNotificationController extends Controller
{
    public function index(Request $request)
    {
        $vendor = $request->vendor;
        if (!$vendor) {
            abort(401, 'Unauthorized');
        }

        $notifications = VendorNotification::where('vendor_id', $vendor->id)
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 20));

        $unreadCount = VendorNotification::where('vendor_id', $vendor->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    public function unreadCount(Request $request)
    {
        $vendor = $request->vendor;
        if (!$vendor) {
            abort(401, 'Unauthorized');
        }

        $count = VendorNotification::where('vendor_id', $vendor->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['unread_count' => $count]);
    }

    public function markRead(Request $request, int $notificationId)
    {
        $vendor = $request->vendor;
        if (!$vendor) {
            abort(401, 'Unauthorized');
        }

        $notification = VendorNotification::where('id', $notificationId)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Notification marked as read']);
    }

    public function markAllRead(Request $request)
    {
        $vendor = $request->vendor;
        if (!$vendor) {
            abort(401, 'Unauthorized');
        }

        VendorNotification::where('vendor_id', $vendor->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function destroy(Request $request, int $notificationId)
    {
        $vendor = $request->vendor;
        if (!$vendor) {
            abort(401, 'Unauthorized');
        }

        $notification = VendorNotification::where('id', $notificationId)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }
}
