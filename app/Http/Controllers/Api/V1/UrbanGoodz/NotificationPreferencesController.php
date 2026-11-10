<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzNotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A signed-in customer's, vendor's or driver's own notification choices. Promotions and SMS are opt-in. */
class NotificationPreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return $this->showFor('customer', (int) $request->user()->id);
    }

    public function update(Request $request): JsonResponse
    {
        return $this->updateFor($request, 'customer', (int) $request->user()->id);
    }

    public function vendorShow(Request $request): JsonResponse
    {
        return $this->showFor('vendor', $this->vendorId($request));
    }

    public function vendorUpdate(Request $request): JsonResponse
    {
        return $this->updateFor($request, 'vendor', $this->vendorId($request));
    }

    public function driverShow(Request $request): JsonResponse
    {
        return $this->showFor('driver', $this->driverId($request));
    }

    public function driverUpdate(Request $request): JsonResponse
    {
        return $this->updateFor($request, 'driver', $this->driverId($request));
    }

    private function vendorId(Request $request): int
    {
        $vendor = $request['vendor'];
        abort_unless($vendor, 401, 'Unauthenticated vendor');

        return (int) $vendor->id;
    }

    private function driverId(Request $request): int
    {
        // dm.api logs the driver in on the delivery_men guard.
        $driver = $request->user('delivery_men') ?? auth('delivery_men')->user();
        abort_unless($driver, 401, 'Unauthenticated driver');

        return (int) $driver->id;
    }

    private function showFor(string $type, int $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->present(UrbanGoodzNotificationPreference::forRecipient($type, $id))]);
    }

    private function updateFor(Request $request, string $type, int $id): JsonResponse
    {
        $data = $request->validate([
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_consent' => ['sometimes', 'boolean'],
            'promotions_opt_in' => ['sometimes', 'boolean'],
        ]);

        $pref = UrbanGoodzNotificationPreference::firstOrNew(['recipient_type' => $type, 'recipient_id' => $id]);
        $pref->fill(array_intersect_key($data, array_flip(['push_enabled', 'email_enabled'])));

        // Record WHEN consent was given or withdrawn: it is the evidence if consent is ever questioned.
        foreach (['sms_consent' => 'sms_consent_at', 'promotions_opt_in' => 'promotions_opt_in_at'] as $flag => $stamp) {
            if (array_key_exists($flag, $data) && (bool) $data[$flag] !== (bool) $pref->{$flag}) {
                $pref->{$flag} = (bool) $data[$flag];
                $pref->{$stamp} = now();
            }
        }
        $pref->save();

        return response()->json(['success' => true, 'data' => $this->present($pref->fresh())]);
    }

    private function present(UrbanGoodzNotificationPreference $p): array
    {
        return [
            'push_enabled' => (bool) $p->push_enabled,
            'email_enabled' => (bool) $p->email_enabled,
            'sms_consent' => (bool) $p->sms_consent,
            'promotions_opt_in' => (bool) $p->promotions_opt_in,
            'note' => 'Order and delivery updates are always sent in the app. Promotions and text messages only if you turn them on.',
        ];
    }
}
