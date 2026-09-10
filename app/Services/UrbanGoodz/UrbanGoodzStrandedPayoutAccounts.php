<?php

namespace App\Services\UrbanGoodz;

use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Stripe Connect accounts for Stranded responders.
 *
 * A responder cannot be paid automatically until Stripe holds a connected
 * account for them with payouts enabled. The platform cannot create that
 * state on their behalf -- Stripe requires the person to submit their own
 * identity and bank details -- so the most this can do is create the account
 * shell, hand back a link, and then keep our copy of the verification state
 * honest.
 *
 * Kept separate from UrbanGoodzStrandedPaymentService on purpose: that class
 * moves money for a specific request, this one manages a responder's standing
 * ability to receive it. Conflating them is how "the payout failed" and "this
 * person was never able to be paid" end up looking like the same error.
 */
class UrbanGoodzStrandedPayoutAccounts
{
    private const API = 'https://api.stripe.com/v1';

    /**
     * Ensure the responder has a connected account, and return a fresh
     * onboarding link for it.
     *
     * Account links are single-use and short-lived by Stripe's design, so this
     * always mints a new one rather than caching. The account id, however, is
     * created once and reused -- creating a second account for someone who
     * already has one orphans their verification progress.
     */
    public function onboardingLink(
        UrbanGoodzStrandedResponder $responder,
        string $returnUrl,
        string $refreshUrl
    ): array {
        if (empty($responder->stripe_account_id)) {
            $account = $this->stripe('POST', '/accounts', [
                'type' => 'express',
                'capabilities[transfers][requested]' => 'true',
                'business_type' => 'individual',
                'metadata[responder_id]' => (string) $responder->id,
                'metadata[user_id]' => (string) $responder->user_id,
            ], 'stranded_connect_account_' . $responder->id);

            if (empty($account['id'])) {
                $message = $account['error']['message'] ?? 'unknown_error';
                Log::error('Could not create Stranded responder Connect account', [
                    'responder' => $responder->id,
                    'stripe_error' => $message,
                ]);

                throw new RuntimeException('Could not start payout setup. ' . $message);
            }

            $responder->forceFill([
                'stripe_account_id' => $account['id'],
                'onboarding_status' => 'pending',
                'onboarding_synced_at' => now(),
            ])->save();
        }

        $link = $this->stripe('POST', '/account_links', [
            'account' => $responder->stripe_account_id,
            'type' => 'account_onboarding',
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
        ]);

        if (empty($link['url'])) {
            $message = $link['error']['message'] ?? 'unknown_error';

            throw new RuntimeException('Could not open payout setup. ' . $message);
        }

        return [
            'account_id' => $responder->stripe_account_id,
            'url' => $link['url'],
            'expires_at' => $link['expires_at'] ?? null,
        ];
    }

    /**
     * Re-read Stripe's view of this account and store it.
     *
     * `payouts_enabled` is Stripe's answer, never ours to infer. A responder
     * can have an account, have submitted everything, and still be withheld
     * pending review -- transferring in that window fails, so we mirror the
     * flag rather than guessing from whether the id exists.
     */
    public function syncStatus(UrbanGoodzStrandedResponder $responder): array
    {
        if (empty($responder->stripe_account_id)) {
            return [
                'onboarding_status' => 'not_started',
                'payouts_enabled' => false,
            ];
        }

        $account = $this->stripe('GET', '/accounts/' . $responder->stripe_account_id);

        if (empty($account['id'])) {
            // Do not downgrade stored state on a transient read failure; a
            // responder who was payable a minute ago should stay payable.
            Log::warning('Could not read Stranded responder Connect account', [
                'responder' => $responder->id,
                'account' => $responder->stripe_account_id,
            ]);

            return [
                'onboarding_status' => $responder->onboarding_status,
                'payouts_enabled' => (bool) $responder->payouts_enabled,
                'stale' => true,
            ];
        }

        $payoutsEnabled = (bool) ($account['payouts_enabled'] ?? false);
        $dueNow = $account['requirements']['currently_due'] ?? [];

        $status = match (true) {
            $payoutsEnabled => 'verified',
            !empty($account['requirements']['disabled_reason']) => 'restricted',
            !empty($dueNow) => 'pending',
            default => 'pending',
        };

        $responder->forceFill([
            'payouts_enabled' => $payoutsEnabled,
            'onboarding_status' => $status,
            'onboarding_synced_at' => now(),
        ])->save();

        return [
            'onboarding_status' => $status,
            'payouts_enabled' => $payoutsEnabled,
            'requirements_due' => array_values($dueNow),
        ];
    }

    /**
     * Credentials are read per call and never held in a property or logged.
     * Mirrors UrbanGoodzStrandedPaymentService::stripe() deliberately -- the
     * settings row is the single source of the key for all Stranded money.
     */
    private function stripe(string $method, string $path, array $fields = [], ?string $idempotencyKey = null): array
    {
        $row = DB::table('addon_settings')->where('key_name', 'stripe')->first();

        if (!$row || !$row->is_active) {
            throw new RuntimeException('Card payments are not available right now.');
        }

        $values = json_decode($row->mode === 'live' ? $row->live_values : $row->test_values, true);
        $key = $values['api_key'] ?? null;

        if (!$key) {
            throw new RuntimeException('Card payments are not configured.');
        }

        $ch = curl_init(self::API . $path);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERPWD => $key . ':',
        ];

        if ($idempotencyKey !== null) {
            $opts[CURLOPT_HTTPHEADER] = ['Idempotency-Key: ' . $idempotencyKey];
        }

        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
        }

        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Could not reach the payment provider. ' . $err);
        }

        return json_decode($body, true) ?: [];
    }
}
