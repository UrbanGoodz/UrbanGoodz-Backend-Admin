<?php

namespace App\Services\UrbanGoodz\Agent;

use App\Models\AiMoniqueSubscription;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class MoniqueEntitlementService
{
    public const DEFAULT_TRIAL_DAYS = 30;
    public const DEFAULT_MONTHLY_PRICE = 49.00;

    /**
     * Resolve or initialize the Monique 30-day trial subscription for an account.
     */
    public function getOrCreateSubscription(string $accountType, int $accountId, ?int $storeId = null): AiMoniqueSubscription
    {
        $query = AiMoniqueSubscription::query()->where('account_type', $accountType);
        if ($accountType === 'vendor') {
            $query->where('vendor_id', $accountId);
        } else {
            $query->where('admin_id', $accountId);
        }

        $sub = $query->first();

        if (!$sub) {
            $trialDays = (int) Config::get('urban_goodz_ai.monique_pricing.trial_days', self::DEFAULT_TRIAL_DAYS);
            $monthlyPrice = (float) Config::get('urban_goodz_ai.monique_pricing.monthly_fee', self::DEFAULT_MONTHLY_PRICE);
            $policy = (string) Config::get('urban_goodz_ai.monique_pricing.post_trial_policy', AiMoniqueSubscription::POLICY_AUTO_CHARGE);
            $autoContinue = (bool) Config::get('urban_goodz_ai.monique_pricing.default_auto_continue', true);

            $now = Carbon::now();
            $sub = AiMoniqueSubscription::create([
                'account_type' => $accountType,
                'vendor_id' => ($accountType === 'vendor') ? $accountId : null,
                'admin_id' => ($accountType !== 'vendor') ? $accountId : null,
                'store_id' => $storeId,
                'status' => AiMoniqueSubscription::STATUS_TRIAL_ACTIVE,
                'trial_start_at' => $now,
                'trial_ends_at' => $now->copy()->addDays($trialDays),
                'auto_continue' => $autoContinue,
                'price_per_month' => $monthlyPrice,
                'post_trial_policy' => $policy,
            ]);

            Log::info("Initialized Monique 30-day free trial for {$accountType} #{$accountId}");
        }

        // Check if trial has expired and transition state if necessary
        $this->evaluateTrialExpiration($sub);

        return $sub;
    }

    /**
     * Check if Monique AI access is authorized for this account.
     */
    public function checkEntitlement(string $accountType, int $accountId): array
    {
        $sub = $this->getOrCreateSubscription($accountType, $accountId);

        $allowed = $sub->isEntitled();
        $daysRemaining = $sub->daysRemaining();

        $message = match ($sub->status) {
            AiMoniqueSubscription::STATUS_TRIAL_ACTIVE => "Monique 30-Day Free Trial active ({$daysRemaining} days remaining).",
            AiMoniqueSubscription::STATUS_ACTIVE_PAID => "Monique Chief of Staff active on paid subscription (\${$sub->price_per_month}/mo).",
            AiMoniqueSubscription::STATUS_TRIAL_EXPIRED => "Monique 30-Day Free Trial has expired. Please upgrade to continue accessing Chief of Staff automation.",
            AiMoniqueSubscription::STATUS_CANCELLED => "Monique subscription has been cancelled. Reactivate to resume service.",
            AiMoniqueSubscription::STATUS_DISABLED => "Monique service is disabled for this account.",
            default => "Monique service is not available for this account.",
        };

        return [
            'allowed' => $allowed,
            'status' => $sub->status,
            'days_remaining' => $daysRemaining,
            'trial_start_at' => $sub->trial_start_at?->toIso8601String(),
            'trial_ends_at' => $sub->trial_ends_at?->toIso8601String(),
            'price_per_month' => (float) $sub->price_per_month,
            'auto_continue' => (bool) $sub->auto_continue,
            'post_trial_policy' => $sub->post_trial_policy,
            'message' => $message,
            'subscription_id' => $sub->id,
        ];
    }

    /**
     * Cancel an active Monique subscription/trial.
     */
    public function cancelSubscription(string $accountType, int $accountId, ?string $reason = null): array
    {
        $sub = $this->getOrCreateSubscription($accountType, $accountId);

        $sub->update([
            'status' => AiMoniqueSubscription::STATUS_CANCELLED,
            'cancelled_at' => Carbon::now(),
            'auto_continue' => false,
            'metadata' => array_merge($sub->metadata ?? [], [
                'cancellation_reason' => $reason,
                'cancelled_at' => now()->toIso8601String(),
            ]),
        ]);

        Log::info("Cancelled Monique subscription for {$accountType} #{$accountId}");

        return [
            'success' => true,
            'status' => AiMoniqueSubscription::STATUS_CANCELLED,
            'message' => 'Monique subscription has been cancelled.',
        ];
    }

    /**
     * Reactivate a cancelled or expired Monique subscription.
     */
    public function reactivateSubscription(string $accountType, int $accountId): array
    {
        $sub = $this->getOrCreateSubscription($accountType, $accountId);

        // If trial still has days left, restore trial; otherwise activate paid
        if ($sub->trial_ends_at && $sub->trial_ends_at->isFuture()) {
            $newStatus = AiMoniqueSubscription::STATUS_TRIAL_ACTIVE;
        } else {
            $newStatus = AiMoniqueSubscription::STATUS_ACTIVE_PAID;
        }

        $sub->update([
            'status' => $newStatus,
            'reactivated_at' => Carbon::now(),
            'auto_continue' => true,
            'metadata' => array_merge($sub->metadata ?? [], [
                'reactivated_at' => now()->toIso8601String(),
            ]),
        ]);

        return [
            'success' => true,
            'status' => $newStatus,
            'message' => 'Monique service has been reactivated.',
        ];
    }

    /**
     * Toggle auto-continue preference.
     */
    public function setAutoContinue(string $accountType, int $accountId, bool $enable): array
    {
        $sub = $this->getOrCreateSubscription($accountType, $accountId);
        $sub->update(['auto_continue' => $enable]);

        return [
            'success' => true,
            'auto_continue' => $enable,
            'message' => $enable ? 'Automatic continuation enabled.' : 'Automatic continuation disabled.',
        ];
    }

    /**
     * Evaluates whether a trial has expired and updates status based on configured post-trial policy.
     */
    private function evaluateTrialExpiration(AiMoniqueSubscription $sub): void
    {
        if ($sub->status !== AiMoniqueSubscription::STATUS_TRIAL_ACTIVE) {
            return;
        }

        if (!$sub->trial_ends_at || $sub->trial_ends_at->isFuture()) {
            return;
        }

        // Trial has expired
        match ($sub->post_trial_policy) {
            AiMoniqueSubscription::POLICY_AUTO_CHARGE => $this->handleAutoChargeTransition($sub),
            AiMoniqueSubscription::POLICY_EXPLICIT_OPT_IN => $sub->update(['status' => AiMoniqueSubscription::STATUS_TRIAL_EXPIRED]),
            AiMoniqueSubscription::POLICY_AUTO_DISABLE => $sub->update(['status' => AiMoniqueSubscription::STATUS_DISABLED]),
            default => $sub->update(['status' => AiMoniqueSubscription::STATUS_TRIAL_EXPIRED]),
        };
    }

    private function handleAutoChargeTransition(AiMoniqueSubscription $sub): void
    {
        if (!$sub->auto_continue || empty($sub->stripe_customer_id)) {
            // No payment method on file -> trial simply lapses
            $sub->update(['status' => AiMoniqueSubscription::STATUS_TRIAL_EXPIRED]);

            return;
        }

        // A stripe_customer_id existing is not a charge: without actually
        // billing the card, auto_charge granted paid access for free. Only
        // flip to active_paid once a real off-session PaymentIntent succeeds.
        if ($this->chargeFirstMonth($sub)) {
            $sub->update(['status' => AiMoniqueSubscription::STATUS_ACTIVE_PAID]);
        } else {
            $sub->update(['status' => AiMoniqueSubscription::STATUS_TRIAL_EXPIRED]);
        }
    }

    private function chargeFirstMonth(AiMoniqueSubscription $sub): bool
    {
        $config = Config::get('urban_goodz_payments.stripe', []);
        $isLive = Config::get('urban_goodz_payments.mode') === 'live_controlled';
        $secretKey = $isLive
            ? ($config['live_secret_key'] ?? '')
            : ($config['secret_key'] ?? '');

        if (empty($config['enabled']) || empty($secretKey)) {
            Log::warning('Monique auto-charge skipped: Stripe is not configured.', [
                'subscription_id' => $sub->id,
            ]);

            return false;
        }

        $amountMinor = (int) round(((float) $sub->price_per_month) * 100);

        if ($amountMinor <= 0) {
            Log::warning('Monique auto-charge skipped: no price configured for subscription.', [
                'subscription_id' => $sub->id,
            ]);

            return false;
        }

        Stripe::setApiKey($secretKey);

        try {
            $customer = StripeCustomer::retrieve($sub->stripe_customer_id);
            $paymentMethod = $customer->invoice_settings->default_payment_method
                ?? $customer->default_source
                ?? null;

            if (!$paymentMethod) {
                Log::warning('Monique auto-charge skipped: customer has no default payment method.', [
                    'subscription_id' => $sub->id,
                    'stripe_customer_id' => $sub->stripe_customer_id,
                ]);

                return false;
            }

            $intent = PaymentIntent::create([
                'amount' => $amountMinor,
                'currency' => 'usd',
                'customer' => $sub->stripe_customer_id,
                'payment_method' => $paymentMethod,
                'off_session' => true,
                'confirm' => true,
                'description' => sprintf(
                    'Monique AI Employee - %s #%d, post-trial auto-continue',
                    $sub->account_type,
                    $sub->vendor_id ?? $sub->admin_id
                ),
                'metadata' => [
                    'ai_monique_subscription_id' => $sub->id,
                ],
            ]);

            if ($intent->status === 'succeeded') {
                $sub->update(['metadata' => array_merge((array) $sub->metadata, [
                    'last_charge_payment_intent_id' => $intent->id,
                    'last_charge_at' => Carbon::now()->toIso8601String(),
                ])]);

                return true;
            }

            Log::warning('Monique auto-charge did not succeed.', [
                'subscription_id' => $sub->id,
                'payment_intent_id' => $intent->id,
                'status' => $intent->status,
            ]);

            return false;
        } catch (ApiErrorException $e) {
            Log::error('Monique auto-charge failed.', [
                'subscription_id' => $sub->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
