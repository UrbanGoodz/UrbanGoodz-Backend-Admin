<?php

namespace App\Domain\Stranded\Payments;

use App\Models\UrbanGoodzStrandedResponder;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

/**
 * Stripe Connect onboarding for Stranded responders.
 *
 * A responder cannot be paid until Stripe has their own account and has
 * confirmed it can receive transfers. This owns exactly that: creating the
 * account, generating the hosted onboarding link, and turning Stripe's
 * account.updated webhook into the three flags releaseEscrow() actually needs
 * to check before it will move money -- charges_enabled, payouts_enabled,
 * details_submitted.
 *
 * Express, not Standard or Custom: onboarding is entirely Stripe-hosted, so
 * this platform never collects or stores identity/banking details itself.
 *
 * Key resolution matches StripePaymentGateway (config-driven, live/test keyed
 * off urban_goodz_payments.mode), not the raw-cURL/addon_settings pattern
 * UrbanGoodzStrandedPaymentService uses for charges -- there is no existing
 * Connect precedent to stay consistent with, so this follows the SDK-based
 * gateway instead of extending the cURL workaround.
 */
class UrbanGoodzStripeConnectService
{
    private bool $enabled;
    private string $secretKey;

    public function __construct()
    {
        $config = config('urban_goodz_payments.stripe');
        $isLive = config('urban_goodz_payments.mode') === 'live_controlled';

        $this->secretKey = ($isLive && !empty($config['live_secret_key']))
            ? $config['live_secret_key']
            : ($config['secret_key'] ?? '');

        $this->enabled = ($config['connect_enabled'] ?? false) && !empty($this->secretKey);

        if ($this->enabled) {
            Stripe::setApiKey($this->secretKey);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Create the responder's Express account if they do not have one yet.
     * Idempotent: a responder who already has an account id is returned as-is.
     */
    public function ensureAccount(UrbanGoodzStrandedResponder $responder): UrbanGoodzStrandedResponder
    {
        $this->assertEnabled();

        if ($responder->stripe_connect_account_id) {
            return $responder;
        }

        try {
            $account = Account::create([
                'type' => 'express',
                'capabilities' => [
                    'transfers' => ['requested' => true],
                    'card_payments' => ['requested' => true],
                ],
                'metadata' => [
                    'urban_goodz_responder_id' => (string) $responder->getKey(),
                    'urban_goodz_user_id' => (string) $responder->user_id,
                    'urban_goodz_responder_type' => (string) $responder->responder_type,
                ],
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stranded Connect account creation failed', [
                'responder_id' => $responder->getKey(),
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Could not start payout onboarding: ' . $e->getMessage());
        }

        $responder->update([
            'stripe_connect_account_id' => $account->id,
            'stripe_onboarding_status' => 'pending',
        ]);

        return $responder->fresh();
    }

    /**
     * A fresh, single-use Stripe-hosted onboarding link. Account Links expire
     * quickly by design, so this is always generated on demand, never stored.
     */
    public function createOnboardingLink(UrbanGoodzStrandedResponder $responder, string $refreshUrl, string $returnUrl): string
    {
        $this->assertEnabled();

        $responder = $this->ensureAccount($responder);

        try {
            $link = AccountLink::create([
                'account' => $responder->stripe_connect_account_id,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stranded Connect onboarding link failed', [
                'responder_id' => $responder->getKey(),
                'account' => $responder->stripe_connect_account_id,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Could not open payout onboarding: ' . $e->getMessage());
        }

        return $link->url;
    }

    /**
     * Sync onboarding/capability flags from a Stripe `account.updated` event's
     * account object. Called from the Connect webhook handler -- this is the
     * only place these columns are ever written after account creation, since
     * capability state is Stripe's to report, not this platform's to guess.
     */
    public function syncFromAccountObject(array $account): void
    {
        $accountId = $account['id'] ?? null;
        if (!$accountId) {
            return;
        }

        $responder = UrbanGoodzStrandedResponder::where('stripe_connect_account_id', $accountId)->first();
        if (!$responder) {
            return;
        }

        $chargesEnabled = (bool) ($account['charges_enabled'] ?? false);
        $payoutsEnabled = (bool) ($account['payouts_enabled'] ?? false);
        $detailsSubmitted = (bool) ($account['details_submitted'] ?? false);

        $status = 'pending';
        if ($chargesEnabled && $payoutsEnabled) {
            $status = 'active';
        } elseif ($detailsSubmitted && !($payoutsEnabled && $chargesEnabled)) {
            // Submitted but Stripe is still reviewing, or has restricted the
            // account (a currently_due/past_due requirement, a rejected
            // capability). Either way, this responder cannot be paid yet.
            $status = 'restricted';
        }

        $responder->update([
            'stripe_charges_enabled' => $chargesEnabled,
            'stripe_payouts_enabled' => $payoutsEnabled,
            'stripe_onboarding_status' => $status,
            'stripe_details_submitted_at' => $detailsSubmitted
                ? ($responder->stripe_details_submitted_at ?? now())
                : null,
        ]);
    }

    private function assertEnabled(): void
    {
        if (!$this->enabled) {
            throw new RuntimeException('Responder payouts are not configured right now.');
        }
    }
}
