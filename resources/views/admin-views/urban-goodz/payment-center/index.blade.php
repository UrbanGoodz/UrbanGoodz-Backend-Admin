@extends('layouts.admin.app')

@section('title', translate('Payment Center - Owner Controls'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span style="color:#161616;">{{ translate('Urban Goodz') }}</span>
                        <span style="color:#ED9914;">{{ translate('Payment Center') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ ucfirst($paymentMode) }}</span>
                    </h1>
                    <p class="mb-0 text-muted">{{ translate('Owner-only payment controls and platform economics') }}</p>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        {{-- Owner Actions --}}
        <div class="row g-2 mb-4">
            <div class="col-md-3">
                <form method="POST" action="{{ route('admin.urban-goodz.payment-center.emergency-disable') }}">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-block"
                            onclick="return confirm('{{ translate('Emergency disable all payments? This cannot be undone from this page.') }}')">
                        <i class="tio-ban mr-1"></i> {{ translate('Emergency Disable') }}
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <form method="POST" action="{{ route('admin.urban-goodz.payment-center.switch-sandbox') }}">
                    @csrf
                    <button type="submit" class="btn btn-warning btn-block"
                            onclick="return confirm('{{ translate('Switch payment mode to Sandbox?') }}')">
                        <i class="tio-shuffle mr-1"></i> {{ translate('Switch to Sandbox') }}
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <form method="POST" action="{{ route('admin.urban-goodz.payment-center.test-webhook') }}">
                    @csrf
                    <button type="submit" class="btn btn-info btn-block">
                        <i class="tio-broadcast mr-1"></i> {{ translate('Test Webhook') }}
                    </button>
                </form>
            </div>
            <div class="col-md-3">
                <form method="POST" action="{{ route('admin.urban-goodz.payment-center.reconciliation.run') }}">
                    @csrf
                    <button type="submit" class="btn btn-success btn-block">
                        <i class="tio-account-balance mr-1"></i> {{ translate('Run Reconciliation') }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Payment Mode --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title" style="color:#ED9914;">{{ translate('Payment Mode') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.payment-center.settings.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Current Mode') }}</label>
                            <select name="payment_mode" class="form-control" id="payment_mode">
                                <option value="disabled" {{ $paymentMode === 'disabled' ? 'selected' : '' }}>
                                    {{ translate('Disabled') }}
                                </option>
                                <option value="sandbox" {{ $paymentMode === 'sandbox' ? 'selected' : '' }}>
                                    {{ translate('Sandbox') }}
                                </option>
                                <option value="live_controlled" disabled title="{{ translate('Live mode is not enabled') }}">
                                    {{ translate('Live (Locked)') }}
                                </option>
                            </select>
                            <small class="text-muted">{{ translate('Live-controlled mode is permanently locked in this interface.') }}</small>
                        </div>
                        <div class="col-md-8">
                            <div class="alert alert-info mb-0">
                                <strong>{{ translate('Mode Rule:') }}</strong>
                                {{ translate('Missing mode defaults to Disabled or Sandbox, never Live.') }}
                            </div>
                        </div>
                    </div>

                    <hr>

                    {{-- Platform Economics --}}
                    <h5 style="color:#ED9914;">{{ translate('Platform Economics') }}</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Platform Fee %') }}</label>
                            <input type="number" step="0.01" min="0" max="50" name="platform_fee_percent"
                                   class="form-control"
                                   value="{{ $settings['platform_fee_percent']['effective_value'] }}">
                            <small class="text-muted">
                                {{ translate('Source:') }} {{ $settings['platform_fee_percent']['source'] }} |
                                {{ translate('Last changed:') }} {{ $settings['platform_fee_percent']['last_changed_at'] }}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Driver Share Source') }}</label>
                            <input type="text" name="driver_share_source" class="form-control"
                                   value="{{ $settings['driver_share_source']['effective_value'] ?? '' }}"
                                   placeholder="{{ translate('e.g., platform_pays') }}">
                            <small class="text-muted">
                                {{ translate('Source:') }} {{ $settings['driver_share_source']['source'] }}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Vendor/Provider Share Source') }}</label>
                            <input type="text" name="vendor_share_source" class="form-control"
                                   value="{{ $settings['vendor_share_source']['effective_value'] ?? '' }}"
                                   placeholder="{{ translate('e.g., platform_pays') }}">
                            <small class="text-muted">
                                {{ translate('Source:') }} {{ $settings['vendor_share_source']['source'] }}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Dispatcher %') }}</label>
                            <input type="number" step="0.01" min="0" max="50" name="dispatcher_percent"
                                   class="form-control"
                                   value="{{ $settings['dispatcher_percent']['effective_value'] }}">
                            <small class="text-muted">
                                {{ translate('Source:') }} {{ $settings['dispatcher_percent']['source'] }}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Creator/Referral %') }}</label>
                            <input type="number" step="0.01" min="0" max="50" name="creator_referral_percent"
                                   class="form-control"
                                   value="{{ $settings['creator_referral_percent']['effective_value'] }}">
                            <small class="text-muted">
                                {{ translate('Source:') }} {{ $settings['creator_referral_percent']['source'] }}
                            </small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Tax Handling') }}</label>
                            <select name="tax_handling" class="form-control">
                                <option value="platform_collects" {{ ($settings['tax_handling']['effective_value'] ?? '') === 'platform_collects' ? 'selected' : '' }}>
                                    {{ translate('Platform Collects') }}
                                </option>
                                <option value="pass_through" {{ ($settings['tax_handling']['effective_value'] ?? '') === 'pass_through' ? 'selected' : '' }}>
                                    {{ translate('Pass Through') }}
                                </option>
                                <option value="excluded" {{ ($settings['tax_handling']['effective_value'] ?? '') === 'excluded' ? 'selected' : '' }}>
                                    {{ translate('Excluded') }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Pass-Through Handling') }}</label>
                            <select name="pass_through_handling" class="form-control">
                                <option value="included" {{ ($settings['pass_through_handling']['effective_value'] ?? '') === 'included' ? 'selected' : '' }}>
                                    {{ translate('Included') }}
                                </option>
                                <option value="excluded" {{ ($settings['pass_through_handling']['effective_value'] ?? '') === 'excluded' ? 'selected' : '' }}>
                                    {{ translate('Excluded') }}
                                </option>
                                <option value="itemized" {{ ($settings['pass_through_handling']['effective_value'] ?? '') === 'itemized' ? 'selected' : '' }}>
                                    {{ translate('Itemized') }}
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Minimum Amount') }}</label>
                            <input type="number" step="0.01" min="0" name="minimum_order_amount"
                                   class="form-control"
                                   value="{{ $settings['minimum_order_amount']['effective_value'] ?? '' }}"
                                   placeholder="{{ translate('No minimum') }}">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Maximum Amount') }}</label>
                            <input type="number" step="0.01" min="0" name="maximum_order_amount"
                                   class="form-control"
                                   value="{{ $settings['maximum_order_amount']['effective_value'] ?? '' }}"
                                   placeholder="{{ translate('No maximum') }}">
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="tio-save mr-1"></i> {{ translate('Save Settings') }}
                        </button>
                        <a href="{{ route('admin.urban-goodz.payment-center.audit-history') }}" class="btn btn-outline-secondary ml-2">
                            <i class="tio-history mr-1"></i> {{ translate('View Audit History') }}
                        </a>
                    </div>
                </form>

                @if($errors->any())
                    <div class="alert alert-danger mt-3">
                        @foreach($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Stripe Configuration Status --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title" style="color:#ED9914;">{{ translate('Stripe Configuration Status') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    @foreach($stripeStatus as $label => $configured)
                        <div class="col-md-4 col-6">
                            <div class="d-flex align-items-center p-2 rounded {{ $configured === 'YES' ? 'bg-soft-success' : 'bg-soft-danger' }}">
                                <span class="badge {{ $configured === 'YES' ? 'badge-success' : 'badge-danger' }} mr-2" style="min-width:32px;">
                                    {{ $configured }}
                                </span>
                                <small>{{ translate(ucwords(str_replace('_', ' ', $label))) }}</small>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="alert alert-warning mt-3 mb-0">
                    <small>
                        <i class="tio-warning mr-1"></i>
                        {{ translate('Secret values are never displayed or exposed in this interface.') }}
                    </small>
                </div>
            </div>
        </div>

        {{-- Webhook Health --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title" style="color:#ED9914;">{{ translate('Webhook Health') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">{{ translate('Endpoint URL') }}</label>
                        <div class="font-weight-bold text-break">{{ $webhookHealth['endpoint_url'] }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Signature Status') }}</label>
                        <div>
                            <span class="badge {{ in_array($webhookHealth['signature_status'], ['Configured', 'Valid'], true) ? 'badge-success' : 'badge-secondary' }}">
                                {{ $webhookHealth['signature_status'] }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Latest Internal Payment Reference') }}</label>
                        <div class="font-weight-bold">{{ $webhookHealth['latest_internal_reference'] ?? translate('N/A') }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Last Received Event') }}</label>
                        <div>{{ $webhookHealth['last_received_event'] ?? translate('None') }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Last Successful Event') }}</label>
                        <div>{{ $webhookHealth['last_successful_event'] ?? translate('None') }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Last Failed Event') }}</label>
                        <div>{{ $webhookHealth['last_failed_event'] ?? translate('None') }}</div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Duplicate/Replay Count') }}</label>
                        <div class="font-weight-bold {{ $webhookHealth['duplicate_replay_count'] > 0 ? 'text-danger' : '' }}">
                            {{ $webhookHealth['duplicate_replay_count'] }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Failed Batch Count') }}</label>
                        <div class="font-weight-bold {{ $webhookHealth['failed_batch_count'] > 0 ? 'text-danger' : '' }}">
                            {{ $webhookHealth['failed_batch_count'] }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Processing Latency') }}</label>
                        <div>{{ $webhookHealth['processing_latency'] ?? translate('N/A') }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if($failedWebhookEvents->isNotEmpty())
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0" style="color:#ED9914;">{{ translate('Failed Webhook Events') }}</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered card-table">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('Provider') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Internal Reference') }}</th>
                                <th>{{ translate('Failure') }}</th>
                                <th>{{ translate('Received') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($failedWebhookEvents as $event)
                                <tr>
                                    <td>{{ $event->provider }}</td>
                                    <td>{{ $event->event_type }}</td>
                                    <td>{{ $event->internal_reference ?? translate('Unavailable') }}</td>
                                    <td>{{ $event->failure_type ?? translate('Unclassified') }}</td>
                                    <td>{{ $event->received_at?->format('M d, Y H:i:s') }}</td>
                                    <td>
                                        @if($event->signature_valid
                                            && in_array($event->event_type, ['payment_intent.succeeded', 'charge.succeeded'], true)
                                            && $event->payable_id
                                            && $event->amount_cents !== null)
                                            <form method="POST" action="{{ route('admin.urban-goodz.payment-center.webhook.retry', $event) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    {{ translate('Retry Safely') }}
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted">{{ translate('Manual review required') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Ledger & Reconciliation Summary --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0" style="color:#ED9914;">{{ translate('Ledger & Reconciliation') }}</h5>
                <a href="{{ route('admin.urban-goodz.payment-center.reconciliation') }}" class="btn btn-sm btn-outline-primary">
                    {{ translate('Full Report') }}
                </a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-2 col-4">
                        <div class="text-center p-3 rounded bg-soft-success">
                            <small class="text-muted">{{ translate('Captured') }}</small>
                            <div class="font-weight-bold h5 mb-0">${{ number_format($ledgerSummary['captured'], 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="text-center p-3 rounded bg-soft-warning">
                            <small class="text-muted">{{ translate('Pending') }}</small>
                            <div class="font-weight-bold h5 mb-0">${{ number_format($ledgerSummary['pending'], 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="text-center p-3 rounded bg-soft-danger">
                            <small class="text-muted">{{ translate('Failed') }}</small>
                            <div class="font-weight-bold h5 mb-0">${{ number_format($ledgerSummary['failed'], 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="text-center p-3 rounded bg-soft-secondary">
                            <small class="text-muted">{{ translate('Refunded') }}</small>
                            <div class="font-weight-bold h5 mb-0">${{ number_format($ledgerSummary['refunded'], 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="text-center p-3 rounded bg-soft-info">
                            <small class="text-muted">{{ translate('Disputed') }}</small>
                            <div class="font-weight-bold h5 mb-0">${{ number_format($ledgerSummary['disputed'], 2) }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-4">
                        <div class="text-center p-3 rounded {{ $ledgerSummary['unreconciled'] > 0 ? 'bg-soft-danger' : 'bg-soft-secondary' }}">
                            <small class="text-muted">{{ translate('Unreconciled') }}</small>
                            <div class="font-weight-bold h5 mb-0">${{ number_format($ledgerSummary['unreconciled'], 2) }}</div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Duplicate Event Count') }}</label>
                        <div class="font-weight-bold {{ $ledgerSummary['duplicate_event_count'] > 0 ? 'text-danger' : '' }}">
                            {{ $ledgerSummary['duplicate_event_count'] }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="text-muted small">{{ translate('Ledger Imbalance') }}</label>
                        <div class="font-weight-bold {{ $ledgerSummary['ledger_imbalance'] != 0 ? 'text-danger' : 'text-success' }}">
                            ${{ number_format($ledgerSummary['ledger_imbalance'], 2) }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">{{ translate('Audit Warnings') }}</label>
                        @if(count($ledgerSummary['audit_warnings']) > 0)
                            @foreach($ledgerSummary['audit_warnings'] as $warning)
                                <div class="text-danger small">
                                    <i class="tio-warning mr-1"></i> {{ $warning }}
                                </div>
                            @endforeach
                        @else
                            <div class="text-success small">
                                <i class="tio-check-circle mr-1"></i> {{ translate('No audit warnings') }}
                            </div>
                        @endif
                    </div>
                </div>

                @if(count($ledgerSummary['deficits']) > 0)
                    <div class="alert alert-danger mt-3">
                        <strong>{{ translate('Deficits Found:') }}</strong>
                        @foreach($ledgerSummary['deficits'] as $deficit)
                            <div>
                                {{ translate('Feature:') }} {{ $deficit['feature'] }} —
                                {{ translate('Captured:') }} ${{ number_format($deficit['captured'], 2) }} —
                                {{ translate('Splits:') }} ${{ number_format($deficit['split_total'], 2) }} —
                                {{ translate('Deficit:') }} ${{ number_format($deficit['deficit'], 2) }}
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0" style="color:#ED9914;">{{ translate('Recent Transactions') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Ledger') }}</th>
                            <th>{{ translate('Operation') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Internal Reference') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentLedgers as $ledger)
                            <tr>
                                <td>{{ $ledger->ledger_number }}</td>
                                <td>{{ $ledger->event_type }}</td>
                                <td>${{ number_format((float) $ledger->amount, 2) }}</td>
                                <td>{{ $ledger->payment_status }}</td>
                                <td>{{ $ledger->reference ?? translate('Unavailable') }}</td>
                                <td>
                                    <a class="btn btn-sm btn-outline-primary"
                                       href="{{ route('admin.urban-goodz.payment-center.transaction-detail', $ledger) }}">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">{{ translate('No payment ledger entries yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Effective Values Reference --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title" style="color:#ED9914;">{{ translate('Effective Values Reference') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Setting') }}</th>
                            <th>{{ translate('Effective Value') }}</th>
                            <th>{{ translate('Source') }}</th>
                            <th>{{ translate('Configured') }}</th>
                            <th>{{ translate('Last Changed By') }}</th>
                            <th>{{ translate('Last Changed At') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($settings as $key => $setting)
                            <tr>
                                <td class="font-weight-bold">{{ translate(ucwords(str_replace('_', ' ', $key))) }}</td>
                                <td>
                                    @if($setting['effective_value'] === null)
                                        <span class="text-muted">—</span>
                                    @elseif(is_bool($setting['effective_value']))
                                        <span class="badge {{ $setting['effective_value'] ? 'badge-success' : 'badge-secondary' }}">
                                            {{ $setting['effective_value'] ? 'Yes' : 'No' }}
                                        </span>
                                    @else
                                        {{ $setting['effective_value'] }}
                                    @endif
                                </td>
                                <td><span class="badge badge-soft-dark">{{ $setting['source'] }}</span></td>
                                <td>
                                    <span class="badge {{ $setting['configured'] ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $setting['configured'] ? translate('Yes') : translate('No') }}
                                    </span>
                                </td>
                                <td>{{ $setting['last_changed_by'] }}</td>
                                <td>{{ $setting['last_changed_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
