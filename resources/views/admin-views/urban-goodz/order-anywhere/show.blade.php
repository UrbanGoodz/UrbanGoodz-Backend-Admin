@extends('layouts.admin.app')

@section('title', translate('Order Anywhere - ') . $request->request_number)

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        {{ translate('Order Anywhere') }}: {{ $request->request_number }}
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.order-anywhere.index') }}" class="btn btn--secondary">{{ translate('Back to list') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Request Details') }}</h5>
                        <span class="badge badge-soft-{{ in_array($request->status, ['completed','approved','vendor_accepted']) ? 'success' : (in_array($request->status, ['rejected','cancelled']) ? 'danger' : 'info') }} fs-14">
                            {{ str_replace('_', ' ', $request->status) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Customer Name') }}</label>
                                <p>{{ $request->customer_name }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Contact') }}</label>
                                <p>{{ $request->customer_phone }} @if($request->customer_email) / {{ $request->customer_email }} @endif</p>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Store / Vendor') }}</label>
                                <p>{{ $request->store_vendor_name ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-6">
                                <label class="font-weight-bold">{{ translate('Store Address / Website') }}</label>
                                <p class="text-wrap">{{ $request->store_vendor_address_or_website ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-12">
                                <label class="font-weight-bold">{{ translate('Request Details') }}</label>
                                <p class="text-wrap">{{ $request->request_details ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-12">
                                <label class="font-weight-bold">{{ translate('Item Details') }}</label>
                                <p class="text-wrap">{{ $request->item_details ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Quantity') }}</label>
                                <p>{{ $request->quantity ?? 1 }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Budget Estimate') }}</label>
                                <p>${{ number_format($request->budget_estimate ?? 0, 2) }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Quote Amount') }}</label>
                                <p>${{ number_format($request->quote_amount ?? 0, 2) }}</p>
                            </div>
                        </div>

                        @if($request->source_urls)
                            <div class="mt-3">
                                <label class="font-weight-bold">{{ translate('Source URLs') }}</label>
                                <ul class="list-unstyled">
                                    @foreach((array)$request->source_urls as $url)
                                        <li><a href="{{ $url }}" target="_blank" class="text-break">{{ $url }}</a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>{{ translate('Financial Summary') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Item') }}</th>
                                        <th>{{ translate('Subtotal') }}</th>
                                        <th>{{ translate('Service Fee') }}</th>
                                        <th>{{ translate('Delivery Fee') }}</th>
                                        <th>{{ translate('Tax') }}</th>
                                        <th>{{ translate('Tip') }}</th>
                                        <th>{{ translate('Total') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>{{ Str::limit($request->item_details, 40) }}</td>
                                        <td>${{ number_format($request->item_subtotal ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->service_fee ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->delivery_fee ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->tax ?? 0, 2) }}</td>
                                        <td>${{ number_format($request->tip ?? 0, 2) }}</td>
                                        <td class="font-weight-bold">
                                            ${{ number_format(($request->item_subtotal ?? 0) + ($request->service_fee ?? 0) + ($request->delivery_fee ?? 0) + ($request->tax ?? 0) + ($request->tip ?? 0), 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @if($request->final_amount)
                            <div class="mt-2 text-right">
                                <strong>{{ translate('Final Amount') }}: ${{ number_format($request->final_amount, 2) }}</strong>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Status & Assignment') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.order-anywhere.status', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Update Status') }}</label>
                                <select name="status" class="form-control">
                                    @foreach($statuses as $s)
                                        <option value="{{ $s }}" {{ $request->status === $s ? 'selected' : '' }}>{{ str_replace('_', ' ', $s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Update') }}</button>
                        </form>

                        <hr>

                        <form action="{{ route('admin.urban-goodz.order-anywhere.assign', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Vendor ID') }}</label>
                                <input type="number" name="vendor_id" class="form-control" value="{{ $request->vendor_id }}" placeholder="{{ translate('Vendor ID') }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Delivery Man ID') }}</label>
                                <input type="number" name="assigned_delivery_man_id" class="form-control" value="{{ $request->assigned_delivery_man_id }}" placeholder="{{ translate('Delivery Man ID') }}">
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Assign') }}</button>
                        </form>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>{{ translate('Admin Notes') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.order-anywhere.notes', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <textarea name="admin_notes" class="form-control" rows="3">{{ $request->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Save Notes') }}</button>
                        </form>
                    </div>
                </div>

                @if($cardRequest)
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>{{ translate('Driver Purchase Card') }}</h5>
                            <span class="badge badge-soft-{{ $cardRequest->card_status === 'active' ? 'success' : (in_array($cardRequest->card_status, ['frozen','cancelled','expired','failed']) ? 'danger' : 'info') }} fs-14">
                                {{ $cardRequest->statusLabel() }}
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="alert {{ $issuingProviderStatus === 'CONFIGURED' ? 'alert-success' : 'alert-warning' }} py-2">
                                <strong>{{ translate('Issuing Provider') }}:</strong>
                                {{ $issuingProviderStatus }}
                                @if($issuingProvider !== 'unconfigured')
                                    ({{ $issuingProvider }})
                                @endif
                            </div>
                            @if(auth('admin')->user()->role_id == 1)
                            <form action="{{ route('admin.urban-goodz.order-anywhere.card-emergency-disable') }}" method="POST" class="mb-3">
                                @csrf
                                <input type="hidden" name="disabled" value="{{ $cardEmergencyDisabled ? 0 : 1 }}">
                                <button type="submit" class="btn {{ $cardEmergencyDisabled ? 'btn-success' : 'btn-danger' }} btn-sm"
                                        onclick="return confirm('{{ $cardEmergencyDisabled ? translate('Clear the emergency disable? Pending eligible requests will resume automatically.') : translate('Emergency-disable all Order Anywhere cards? Active cards will be revoked.') }}')">
                                    {{ $cardEmergencyDisabled ? translate('Clear Emergency Disable') : translate('Emergency Disable Cards') }}
                                </button>
                            </form>
                            @endif
                            <div class="row g-2">
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Provider') }}</label>
                                    <p class="mb-1">{{ $cardRequest->provider === 'unconfigured' ? translate('NOT CONFIGURED') : ($cardRequest->provider ?? translate('N/A')) }}</p>
                                </div>
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Assigned Driver') }}</label>
                                    <p class="mb-1">{{ $cardRequest->driver?->f_name ?? translate('N/A') }} {{ $cardRequest->driver?->l_name ?? '' }}</p>
                                </div>
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Spending Limit') }}</label>
                                    <p class="mb-1">{{ $cardRequest->currency ?? 'USD' }} ${{ number_format($cardRequest->spending_limit ?? 0, 2) }}</p>
                                </div>
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Remaining Balance') }}</label>
                                    <p class="mb-1">{{ $cardRequest->currency ?? 'USD' }} ${{ number_format($cardRequest->remainingBalance(), 2) }}</p>
                                </div>
                                @if($cardRequest->authorized_amount > 0)
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Authorized Amount') }}</label>
                                    <p class="mb-1">${{ number_format($cardRequest->authorized_amount, 2) }}</p>
                                </div>
                                @endif
                                @if($cardRequest->captured_amount > 0)
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Captured Amount') }}</label>
                                    <p class="mb-1">${{ number_format($cardRequest->captured_amount, 2) }}</p>
                                </div>
                                @endif
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Card Type') }}</label>
                                    <p class="mb-1">{{ $cardRequest->card_type ?? translate('Virtual') }}</p>
                                </div>
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Single Use') }}</label>
                                    <p class="mb-1">{{ $cardRequest->payment_count_limit === 1 ? translate('Cancel after one payment') : translate('Provider lifecycle controlled') }}</p>
                                </div>
                                @if($cardRequest->expires_at)
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Expires') }}</label>
                                    <p class="mb-1">{{ $cardRequest->expires_at->format('M d, Y H:i') }}</p>
                                </div>
                                @endif
                                @if($cardRequest->created_at)
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Created') }}</label>
                                    <p class="mb-1">{{ $cardRequest->created_at->format('M d, Y H:i') }}</p>
                                </div>
                                @endif
                                @if($cardRequest->issued_at)
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Issued') }}</label>
                                    <p class="mb-1">{{ $cardRequest->issued_at->format('M d, Y H:i') }}</p>
                                </div>
                                @endif
                                @if($cardRequest->receipt_submitted_at)
                                <div class="col-12">
                                    <label class="font-weight-bold small">{{ translate('Receipt') }}</label>
                                    <p class="mb-1">
                                        <a href="{{ route('admin.urban-goodz.order-anywhere.card-receipt', $request->id) }}">
                                            {{ translate('Download private receipt') }}
                                        </a>
                                        — ${{ number_format($cardRequest->receipt_total ?? 0, 2) }}
                                    </p>
                                </div>
                                @endif
                                @if($cardRequest->failure_reason)
                                <div class="col-12">
                                    <label class="font-weight-bold small text-danger">{{ translate('Failure Reason') }}</label>
                                    <p class="mb-1 text-danger">{{ $cardRequest->failure_reason }}</p>
                                </div>
                                @endif
                                @if(in_array($cardRequest->card_status, ['provider_pending', 'awaiting_provider_configuration', 'issuance_pending', 'issuance_retry_pending']))
                                <div class="col-12">
                                    <div class="alert alert-warning mb-0 py-2 small">
                                        <i class="tio-info-outined"></i>
                                        {{ $cardRequest->card_status === 'awaiting_provider_configuration'
                                            ? translate('The workflow is eligible and waiting for owner provider configuration. No card credentials exist.')
                                            : translate('Automatic card issuance is pending. It is not yet usable for purchases.') }}
                                    </div>
                                </div>
                                @endif
                                @if($cardRequest->card_status === 'disabled')
                                <div class="col-12">
                                    <div class="alert alert-secondary mb-0 py-2 small">
                                        <i class="tio-info-outined"></i>
                                        {{ translate('Card issuing is currently disabled on this platform.') }}
                                    </div>
                                </div>
                                @endif
                                @if($cardRequest->last4)
                                <div class="col-6">
                                    <label class="font-weight-bold small">{{ translate('Card Last 4') }}</label>
                                    <p class="mb-1">**** **** **** {{ $cardRequest->last4 }}</p>
                                </div>
                                @endif
                            </div>

                            <hr class="my-2">

                            <div class="d-flex flex-wrap gap-2">
                                @if(in_array($cardRequest->card_status, ['requested', 'provider_pending', 'issued', 'active']))
                                    @if(auth('admin')->user()->role_id == 1 || \App\CentralLogics\Helpers::module_permission_check('urban_goodz_order_anywhere_freeze_card'))
                                    <form action="{{ route('admin.urban-goodz.order-anywhere.freeze-card', $request->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('{{ translate('Freeze this driver card?') }}')">
                                            <i class="tio-pause"></i> {{ translate('Freeze Card') }}
                                        </button>
                                    </form>
                                    @endif
                                @endif

                                @if(!in_array($cardRequest->card_status, ['cancelled', 'used', 'reconciled']))
                                    @if(auth('admin')->user()->role_id == 1 || \App\CentralLogics\Helpers::module_permission_check('urban_goodz_order_anywhere_cancel_card'))
                                    <form action="{{ route('admin.urban-goodz.order-anywhere.cancel-card', $request->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('{{ translate('Cancel this driver card? This cannot be undone.') }}')">
                                            <i class="tio-delete"></i> {{ translate('Cancel Card') }}
                                        </button>
                                    </form>
                                    @endif
                                @endif

                                @if(in_array($cardRequest->card_status, ['used', 'frozen']))
                                    @if(auth('admin')->user()->role_id == 1 || \App\CentralLogics\Helpers::module_permission_check('urban_goodz_order_anywhere_reconcile_card'))
                                    <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#reconcileCardModal">
                                        <i class="tio-checkmark"></i> {{ translate('Reconcile Card') }}
                                    </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>

                    @if(in_array($cardRequest->card_status, ['used', 'frozen']))
                    <div class="modal fade" id="reconcileCardModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="{{ route('admin.urban-goodz.order-anywhere.reconcile-card', $request->id) }}" method="POST">
                                    @csrf
                                    <div class="modal-header">
                                        <h5>{{ translate('Reconcile Driver Card') }}</h5>
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="form-group">
                                            <label>{{ translate('Captured Amount') }}</label>
                                            <input type="number" step="0.01" name="captured_amount" class="form-control" value="{{ $cardRequest->captured_amount }}">
                                        </div>
                                        <div class="form-group">
                                            <label>{{ translate('Refunded Amount') }}</label>
                                            <input type="number" step="0.01" name="refunded_amount" class="form-control" value="0">
                                        </div>
                                        <div class="form-group">
                                            <label>{{ translate('Merchant Name') }}</label>
                                            <input type="text" name="merchant_name" class="form-control" value="{{ $cardRequest->merchant_name }}">
                                        </div>
                                        <div class="form-group">
                                            <label>{{ translate('Receipt Total') }}</label>
                                            <input type="number" step="0.01" name="receipt_total" class="form-control">
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn--secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                                        <button type="submit" class="btn btn--primary">{{ translate('Reconcile') }}</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endif

                    @if(auth('admin')->user()->role_id == 1)
                    @if(!$cardRequest || in_array($cardRequest->card_status, ['cancelled', 'expired', 'used', 'reconciled', 'failed']))
                        @if(in_array($request->payment_status, ['captured', 'authorized']) && $request->assigned_delivery_man_id)
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5>{{ translate('Owner Recovery') }}</h5>
                            </div>
                            <div class="card-body">
                                @if($issuingMode === 'disabled')
                                    <div class="alert alert-secondary mb-0 py-2 small">
                                        <i class="tio-info-outined"></i>
                                        {{ translate('Card issuing is currently disabled. Enable it in payment configuration.') }}
                                    </div>
                                @else
                                    <form action="{{ route('admin.urban-goodz.order-anywhere.request-card', $request->id) }}" method="POST">
                                        @csrf
                                        <div class="form-group">
                                            <label>{{ translate('Spending Limit (optional)') }}</label>
                                            <input type="number" step="0.01" name="spending_limit" class="form-control" placeholder="{{ translate('Default: Order amount') }}">
                                        </div>
                                        @if($issuingMode === 'live_controlled')
                                            <div class="alert alert-warning mb-2 py-1 small">
                                                <i class="tio-alert"></i>
                                                {{ translate('Live mode: max $') }}{{ number_format($liveMaxAmount ?? 50, 2) }}
                                            </div>
                                        @endif
                                        <button type="submit" class="btn btn--primary btn-block" onclick="return confirm('{{ translate('Re-run the automatic eligibility and idempotency checks?') }}')">
                                            <i class="tio-refresh"></i> {{ translate('Retry Automatic Issuance') }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @else
                        <div class="card mt-3">
                            <div class="card-body">
                                <div class="alert alert-info mb-0 py-2 small">
                                    <i class="tio-info-outined"></i>
                                    {{ translate('A driver must be assigned and payment captured before a purchase card can be requested.') }}
                                </div>
                            </div>
                        </div>
                        @endif
                    @endif
                    @endif
                @else
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>{{ translate('Driver Purchase Card') }}</h5>
                        </div>
                        <div class="card-body">
                            @if($issuingMode === 'disabled')
                                <div class="alert alert-secondary mb-0 py-2 small">
                                    <i class="tio-info-outined"></i>
                                    {{ translate('Card issuing is currently disabled on this platform.') }}
                                </div>
                            @else
                                @if(auth('admin')->user()->role_id == 1)
                                @if(in_array($request->payment_status, ['captured', 'authorized']) && $request->assigned_delivery_man_id)
                                    <form action="{{ route('admin.urban-goodz.order-anywhere.request-card', $request->id) }}" method="POST">
                                        @csrf
                                        <div class="form-group">
                                            <label>{{ translate('Spending Limit (optional)') }}</label>
                                            <input type="number" step="0.01" name="spending_limit" class="form-control" placeholder="{{ translate('Default: Order amount') }}">
                                        </div>
                                        @if($issuingMode === 'live_controlled')
                                            <div class="alert alert-warning mb-2 py-1 small">
                                                <i class="tio-alert"></i>
                                                {{ translate('Live mode: max $') }}{{ number_format($liveMaxAmount ?? 50, 2) }}
                                            </div>
                                        @endif
                                        <button type="submit" class="btn btn--primary btn-block" onclick="return confirm('{{ translate('Re-run the automatic eligibility and idempotency checks?') }}')">
                                            <i class="tio-refresh"></i> {{ translate('Retry Automatic Issuance') }}
                                        </button>
                                    </form>
                                @else
                                    <div class="alert alert-info mb-0 py-2 small">
                                        <i class="tio-info-outined"></i>
                                        {{ translate('A driver must be assigned and payment captured before a purchase card can be requested.') }}
                                    </div>
                                @endif
                                @else
                                    <div class="text-muted small">
                                        {{ translate('You do not have permission to request driver cards.') }}
                                    </div>
                                @endif
                    @endif

                    @if(($cardAuditHistory ?? collect())->isNotEmpty())
                    <div class="card mt-3">
                        <div class="card-header"><h5>{{ translate('Purchase Card Audit History') }}</h5></div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead><tr><th>{{ translate('Time') }}</th><th>{{ translate('Event') }}</th><th>{{ translate('Actor') }}</th><th>{{ translate('Details') }}</th></tr></thead>
                                <tbody>
                                @foreach($cardAuditHistory as $audit)
                                    <tr>
                                        <td>{{ $audit->created_at?->format('M d, Y H:i:s') }}</td>
                                        <td>{{ $audit->event }}</td>
                                        <td>{{ $audit->causer_type ? class_basename($audit->causer_type).' #'.$audit->causer_id : translate('System') }}</td>
                                        <td>{{ $audit->description }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                        </div>
                    </div>
                @endif

                <div class="card mt-3">
                    <div class="card-header">
                        <h5>{{ translate('Quote / Capture / Refund') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.order-anywhere.quote', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Quote Amount') }}</label>
                                <input type="number" step="0.01" name="quote_amount" class="form-control" value="{{ $request->quote_amount }}" required>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Create Quote') }}</button>
                        </form>
                        <hr>
                        <form action="{{ route('admin.urban-goodz.order-anywhere.capture', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Capture Amount') }}</label>
                                <input type="number" step="0.01" name="captured_amount" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-success btn-block">{{ translate('Capture Payment') }}</button>
                        </form>
                        <hr>
                        <form action="{{ route('admin.urban-goodz.order-anywhere.refund', $request->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Refund Amount') }}</label>
                                <input type="number" step="0.01" name="refund_amount" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-danger btn-block">{{ translate('Refund') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        @if(count($ledgers) > 0)
            <div class="card mt-3">
                <div class="card-header">
                    <h5>{{ translate('Payment Ledgers') }}</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>{{ translate('Ledger #') }}</th>
                                    <th>{{ translate('Event') }}</th>
                                    <th>{{ translate('Direction') }}</th>
                                    <th>{{ translate('Amount') }}</th>
                                    <th>{{ translate('Status') }}</th>
                                    <th>{{ translate('Date') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ledgers as $l)
                                    <tr>
                                        <td>{{ $l->ledger_number }}</td>
                                        <td>{{ $l->event_type }}</td>
                                        <td>{{ $l->direction }}</td>
                                        <td>${{ number_format($l->amount, 2) }}</td>
                                        <td>{{ $l->payment_status }}</td>
                                        <td>{{ $l->created_at->format('M d, Y H:i') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
