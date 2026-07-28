<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\PaymentFinalizationConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePaymentSettingsRequest;
use App\Models\OrderAnywhereRequest;
use App\Models\UrbanGoodzPaymentLedger;
use App\Models\UrbanGoodzPaymentSetting;
use App\Models\UrbanGoodzPaymentSettingAudit;
use App\Models\UrbanGoodzPaymentSplit;
use App\Models\UrbanGoodzPaymentTransaction;
use App\Models\UrbanGoodzWebhookEvent;
use App\Services\Payments\PaymentSettings;
use App\Services\UrbanGoodzPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class PaymentCenterController extends Controller
{
    private const OWNER_SETTING_DEFAULTS = [
        'payment_mode' => ['default' => 'sandbox', 'config' => 'urban_goodz_payments.mode', 'type' => 'string'],
        'platform_fee_percent' => ['default' => 10.0, 'config' => 'urban_goodz_payments.default_platform_fee_percent', 'type' => 'decimal'],
        'driver_share_source' => ['default' => 'compensation_rule', 'config' => null, 'type' => 'string'],
        'vendor_share_source' => ['default' => 'order_financial_snapshot', 'config' => null, 'type' => 'string'],
        'dispatcher_percent' => ['default' => 0.0, 'config' => 'urban_goodz_payments.default_dispatcher_commission_rate', 'type' => 'decimal'],
        'creator_referral_percent' => ['default' => 0.0, 'config' => null, 'type' => 'decimal'],
        'tax_handling' => ['default' => 'pass_through', 'config' => null, 'type' => 'string'],
        'pass_through_handling' => ['default' => 'itemized', 'config' => null, 'type' => 'string'],
        'minimum_order_amount' => ['default' => null, 'config' => null, 'type' => 'decimal'],
        'maximum_order_amount' => ['default' => null, 'config' => null, 'type' => 'decimal'],
    ];

    public function __construct(private readonly PaymentSettings $paymentSettings)
    {
        $this->middleware('admin');
    }

    public function index()
    {
        $this->requireOwner();

        return view('admin-views.urban-goodz.payment-center.index', [
            'settings' => $this->effectiveSettings(),
            'paymentMode' => $this->paymentSettings->mode(),
            'stripeStatus' => $this->stripeConfigurationStatus(),
            'webhookHealth' => $this->webhookHealth(),
            'ledgerSummary' => $this->ledgerSummary(),
            'recentLedgers' => Schema::hasTable('urban_goodz_payment_ledgers')
                ? UrbanGoodzPaymentLedger::latest()->limit(10)->get()
                : collect(),
            'failedWebhookEvents' => Schema::hasTable('urban_goodz_webhook_events')
                ? UrbanGoodzWebhookEvent::where('processing_status', 'failed')->latest()->limit(10)->get()
                : collect(),
        ]);
    }

    public function updateSettings(UpdatePaymentSettingsRequest $request)
    {
        $this->requireOwner();

        foreach ($request->validated() as $key => $value) {
            if (array_key_exists($key, self::OWNER_SETTING_DEFAULTS)) {
                UrbanGoodzPaymentSetting::setValue($key, $value, 'owner', auth('admin')->id());
            }
        }

        return back()->with('success', translate('Payment settings updated successfully.'));
    }

    public function emergencyDisable()
    {
        $this->requireOwner();
        UrbanGoodzPaymentSetting::setValue('payment_mode', 'disabled', 'owner', auth('admin')->id());

        return back()->with('success', translate('Payments have been emergency disabled.'));
    }

    public function switchToSandbox()
    {
        $this->requireOwner();
        UrbanGoodzPaymentSetting::setValue('payment_mode', 'sandbox', 'owner', auth('admin')->id());

        return back()->with('success', translate('Payment mode switched to Sandbox.'));
    }

    public function testWebhook()
    {
        $this->requireOwner();

        $checks = [
            'route_registered' => collect(Route::getRoutes())->contains(
                fn ($route) => $route->uri() === 'api/v1/payments/webhooks/{provider}'
            ),
            'receipt_storage_ready' => Schema::hasTable('urban_goodz_webhook_events'),
            'stripe_webhook_secret_configured' => filled(config('urban_goodz_payments.stripe.webhook_secret')),
            'payment_mode' => $this->paymentSettings->mode(),
        ];
        $passed = $checks['route_registered']
            && $checks['receipt_storage_ready']
            && $checks['stripe_webhook_secret_configured']
            && $checks['payment_mode'] !== 'live_controlled';

        $this->auditAction('webhook_diagnostic', $passed ? 'passed' : 'failed', $checks);

        return back()->with(
            $passed ? 'success' : 'error',
            translate($passed
                ? 'Webhook endpoint, signature configuration, and receipt storage passed diagnostics.'
                : 'Webhook diagnostics failed. Review the sanitized webhook health status.')
        );
    }

    public function reconciliation()
    {
        $this->requireOwner();

        return view('admin-views.urban-goodz.payment-center.reconciliation', [
            'summary' => $this->ledgerSummary(),
            'ledgerSummary' => $this->ledgerSummary(),
        ]);
    }

    public function runReconciliation()
    {
        $this->requireOwner();
        $summary = $this->ledgerSummary();
        $this->auditAction('reconciliation', 'completed', [
            'ledger_imbalance_cents' => $summary['ledger_imbalance_cents'],
            'unreconciled_count' => $summary['unreconciled_count'],
            'deficit_count' => count($summary['deficits']),
            'warning_count' => count($summary['audit_warnings']),
        ]);

        return redirect()
            ->route('admin.urban-goodz.payment-center.reconciliation')
            ->with('success', translate('Reconciliation completed and recorded in the audit trail.'));
    }

    public function retryFailedWebhook(
        UrbanGoodzWebhookEvent $webhookEvent,
        UrbanGoodzPaymentService $payments
    ) {
        $this->requireOwner();

        abort_unless(
            $webhookEvent->processing_status === 'failed'
                && $webhookEvent->signature_valid === true
                && $webhookEvent->provider === 'stripe'
                && in_array($webhookEvent->event_type, ['payment_intent.succeeded', 'charge.succeeded'], true)
                && $webhookEvent->payable_type === OrderAnywhereRequest::class
                && $webhookEvent->payable_id
                && $webhookEvent->amount_cents !== null,
            422,
            'This receipt cannot be retried safely.'
        );

        $payment = OrderAnywhereRequest::findOrFail($webhookEvent->payable_id);

        try {
            $result = $payments->finalizeCustomerPayment($payment, [
                'captured_amount' => $webhookEvent->amount_cents / 100,
                'capture_reference' => $webhookEvent->payment_intent_id,
                'psp_reference' => $webhookEvent->payment_intent_id,
                'payment_intent_id' => $webhookEvent->payment_intent_id,
                'charge_id' => $webhookEvent->charge_id,
                'internal_reference' => $webhookEvent->internal_reference,
                'source' => 'webhook',
                'capture_idempotency_key' => "webhook:stripe:{$webhookEvent->event_id}",
            ]);
        } catch (PaymentFinalizationConflictException) {
            $this->auditAction('webhook_retry', 'identity_conflict', ['receipt_id' => $webhookEvent->id]);
            return back()->with('error', translate('Retry stopped because the payment identity conflicts with the stored record.'));
        }

        $webhookEvent->update([
            'processing_status' => $result->alreadyProcessed ? 'already_processed' : 'processed',
            'failure_type' => null,
            'processed_at' => now(),
        ]);
        $this->auditAction('webhook_retry', $webhookEvent->processing_status, ['receipt_id' => $webhookEvent->id]);

        return back()->with('success', translate('Failed webhook retry completed safely.'));
    }

    public function transactionDetail(UrbanGoodzPaymentLedger $ledger)
    {
        $this->requireOwner();

        return view('admin-views.urban-goodz.payment-center.transaction-detail', [
            'ledger' => $ledger->load('splits'),
        ]);
    }

    public function auditHistory()
    {
        $this->requireOwner();

        return view('admin-views.urban-goodz.payment-center.audit-history', [
            'audits' => UrbanGoodzPaymentSettingAudit::with('admin')->latest()->paginate(50),
        ]);
    }

    private function requireOwner(): void
    {
        abort_unless((int) auth('admin')->user()?->role_id === 1, 403, 'Owner access required.');
    }

    private function effectiveSettings(): array
    {
        $stored = Schema::hasTable('urban_goodz_payment_settings')
            ? UrbanGoodzPaymentSetting::with('auditor')->get()->keyBy('setting_key')
            : collect();

        return collect(self::OWNER_SETTING_DEFAULTS)->map(function (array $meta, string $key) use ($stored) {
            $setting = $stored->get($key);
            $fallback = $meta['config'] ? config($meta['config'], $meta['default']) : $meta['default'];
            $value = $setting
                ? $this->castValue($setting->value, $setting->value_type)
                : $fallback;

            if ($key === 'payment_mode' && ! in_array($value, ['disabled', 'sandbox', 'live_controlled'], true)) {
                $value = 'disabled';
            }

            return [
                'effective_value' => $value,
                'source' => $setting?->source ?? ($meta['config'] ? 'environment/config' : 'code default'),
                'configured' => (bool) $setting,
                'last_changed_by' => $setting?->auditor
                    ? trim($setting->auditor->f_name . ' ' . $setting->auditor->l_name)
                    : 'Not owner-configured',
                'last_changed_at' => $setting?->last_changed_at?->format('M d, Y H:i:s') ?? 'Never',
            ];
        })->all();
    }

    private function castValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'float', 'decimal' => (float) $value,
            'json' => json_decode((string) $value, true),
            default => $value,
        };
    }

    private function stripeConfigurationStatus(): array
    {
        return collect([
            'sandbox_publishable_key' => config('urban_goodz_payments.stripe.publishable_key'),
            'sandbox_secret_key' => config('urban_goodz_payments.stripe.secret_key'),
            'sandbox_webhook_secret' => config('urban_goodz_payments.stripe.webhook_secret'),
            'live_publishable_key' => config('urban_goodz_payments.stripe.live_publishable_key'),
            'live_secret_key' => config('urban_goodz_payments.stripe.live_secret_key'),
            'live_webhook_secret' => config('urban_goodz_payments.stripe.live_webhook_secret'),
        ])->map(fn ($value) => filled($value) ? 'YES' : 'NO')->all();
    }

    private function webhookHealth(): array
    {
        $empty = [
            'endpoint_url' => url('/api/v1/payments/webhooks/stripe'),
            'last_received_event' => null,
            'last_successful_event' => null,
            'last_failed_event' => null,
            'signature_status' => filled(config('urban_goodz_payments.stripe.webhook_secret')) ? 'Configured' : 'Not Configured',
            'duplicate_replay_count' => 0,
            'failed_batch_count' => 0,
            'processing_latency' => null,
            'latest_internal_reference' => null,
        ];

        if (! Schema::hasTable('urban_goodz_webhook_events')) {
            return $empty;
        }

        $latest = UrbanGoodzWebhookEvent::latest('received_at')->first();
        $success = UrbanGoodzWebhookEvent::whereIn('processing_status', ['processed', 'handled', 'already_processed'])
            ->latest('processed_at')->first();
        $failed = UrbanGoodzWebhookEvent::whereIn('processing_status', ['failed', 'unmatched'])
            ->latest('processed_at')->first();

        return array_merge($empty, [
            'last_received_event' => $latest?->received_at?->format('M d, Y H:i:s'),
            'last_successful_event' => $success?->processed_at?->format('M d, Y H:i:s'),
            'last_failed_event' => $failed?->processed_at?->format('M d, Y H:i:s'),
            'signature_status' => $latest
                ? ($latest->signature_valid ? 'Valid' : 'Invalid')
                : $empty['signature_status'],
            'duplicate_replay_count' => (int) UrbanGoodzWebhookEvent::sum('duplicate_count')
                + UrbanGoodzWebhookEvent::where('processing_status', 'already_processed')->count(),
            'failed_batch_count' => UrbanGoodzWebhookEvent::whereIn('processing_status', ['failed', 'unmatched'])->count(),
            'processing_latency' => $latest?->processing_latency_ms !== null
                ? $latest->processing_latency_ms . ' ms'
                : null,
            'latest_internal_reference' => $latest?->internal_reference,
        ]);
    }

    private function ledgerSummary(): array
    {
        $empty = [
            'captured' => 0.0,
            'pending' => 0.0,
            'failed' => 0.0,
            'refunded' => 0.0,
            'disputed' => 0.0,
            'unreconciled' => 0.0,
            'unreconciled_count' => 0,
            'duplicate_event_count' => 0,
            'ledger_imbalance' => 0.0,
            'ledger_imbalance_cents' => 0,
            'deficits' => [],
            'audit_warnings' => [],
        ];

        if (! Schema::hasTable('urban_goodz_payment_ledgers')) {
            return $empty;
        }

        $debits = (float) UrbanGoodzPaymentLedger::where('direction', 'debit')->sum('amount');
        $credits = (float) UrbanGoodzPaymentLedger::where('direction', 'credit')->sum('amount');
        $imbalance = round($debits - $credits, 2);
        $unreconciled = UrbanGoodzPaymentLedger::where('payment_status', 'unmatched');
        $hasTransactions = Schema::hasTable('urban_goodz_payment_transactions');

        $summary = array_merge($empty, [
            'captured' => (float) UrbanGoodzPaymentLedger::where('event_type', 'capture')->where('payment_status', 'captured')->sum('amount'),
            'pending' => $hasTransactions
                ? (float) UrbanGoodzPaymentTransaction::whereIn('internal_status', ['pending', 'authorized'])->sum('amount_minor') / 100
                : 0.0,
            'failed' => $hasTransactions
                ? (float) UrbanGoodzPaymentTransaction::where('internal_status', 'failed')->sum('amount_minor') / 100
                : 0.0,
            'refunded' => (float) UrbanGoodzPaymentLedger::where('event_type', 'refund')->where('direction', 'debit')->sum('amount'),
            'disputed' => (float) UrbanGoodzPaymentLedger::where('payment_status', 'disputed')->sum('amount'),
            'unreconciled' => (float) (clone $unreconciled)->sum('amount'),
            'unreconciled_count' => (clone $unreconciled)->count(),
            'duplicate_event_count' => Schema::hasTable('urban_goodz_webhook_events')
                ? (int) UrbanGoodzWebhookEvent::sum('duplicate_count')
                    + UrbanGoodzWebhookEvent::where('processing_status', 'already_processed')->count()
                : 0,
            'ledger_imbalance' => $imbalance,
            'ledger_imbalance_cents' => (int) round($imbalance * 100),
        ]);
        $summary['deficits'] = $this->deficits();
        $summary['audit_warnings'] = $this->auditWarnings($summary);

        return $summary;
    }

    private function deficits(): array
    {
        if (! Schema::hasTable('urban_goodz_payment_splits')) {
            return [];
        }

        return UrbanGoodzPaymentLedger::where('event_type', 'capture')
            ->where('payment_status', 'captured')
            ->get()
            ->map(function (UrbanGoodzPaymentLedger $capture) {
                $allocated = (float) UrbanGoodzPaymentSplit::where('payable_type', $capture->payable_type)
                    ->where('payable_id', $capture->payable_id)
                    ->where('status', 'released')
                    ->sum('amount');
                $deficit = round((float) $capture->amount - $allocated, 2);

                return $deficit > 0.009 ? [
                    'feature' => $capture->feature,
                    'captured' => (float) $capture->amount,
                    'split_total' => $allocated,
                    'deficit' => $deficit,
                ] : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function auditWarnings(array $summary): array
    {
        $warnings = [];

        if ($this->paymentSettings->mode() === 'live_controlled') {
            $warnings[] = 'Live-controlled mode is active outside this locked interface.';
        }
        if (! filled(config('urban_goodz_payments.stripe.webhook_secret'))) {
            $warnings[] = 'Stripe sandbox webhook secret is not configured.';
        }
        if ($summary['ledger_imbalance_cents'] !== 0) {
            $warnings[] = 'Ledger debits and credits are not balanced.';
        }
        if ($summary['unreconciled_count'] > 0) {
            $warnings[] = 'Unreconciled payment entries require review.';
        }

        return $warnings;
    }

    private function auditAction(string $action, string $result, array $metadata): void
    {
        UrbanGoodzPaymentSettingAudit::create([
            'setting_key' => '__owner_action__',
            'old_value' => null,
            'new_value' => $result,
            'old_source' => null,
            'new_source' => 'owner',
            'admin_id' => auth('admin')->id(),
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
