<?php

namespace App\Http\Controllers;

use App\Models\UrbanGoodzOrderAnywhereCardRevealSession;
use App\Services\Payments\CardIssuingProviderManager;
use Illuminate\Http\Request;

class OrderAnywhereCardRevealController extends Controller
{
    public function show(string $token)
    {
        $session = $this->session($token);
        return view('admin-views.urban-goodz.order-anywhere.card-reveal', [
            'token' => $token,
            'cardId' => $session->cardRequest->provider_card_id,
            'publishableKey' => config('urban_goodz_payments.stripe.publishable_key'),
        ]);
    }

    public function ephemeralKey(Request $request, string $token, CardIssuingProviderManager $manager)
    {
        $data = $request->validate(['nonce' => ['required', 'string', 'max:500']]);
        $session = $this->session($token);
        $result = $manager->resolve('stripe')->createSecureRevealSession(
            $session->cardRequest->provider_card_id,
            $data['nonce']
        );
        abort_unless(($result['success'] ?? false) === true, 422, 'Secure reveal could not be started.');
        $session->update(['first_used_at' => $session->first_used_at ?? now()]);
        return response()->json([
            'ephemeral_key_secret' => $result['ephemeral_key_secret'],
            'expires_at' => $result['expires_at'],
        ]);
    }

    private function session(string $token): UrbanGoodzOrderAnywhereCardRevealSession
    {
        $session = UrbanGoodzOrderAnywhereCardRevealSession::with('cardRequest.orderAnywhereRequest')
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
        abort_unless($session->isUsable(), 410, 'Reveal session expired.');
        $card = $session->cardRequest;
        abort_unless(
            $card
            && (int) $card->delivery_man_id === (int) $session->delivery_man_id
            && (int) $card->orderAnywhereRequest?->assigned_delivery_man_id === (int) $session->delivery_man_id
            && in_array($card->card_status, ['issued', 'active', 'authorized'], true),
            403
        );
        return $session;
    }
}
