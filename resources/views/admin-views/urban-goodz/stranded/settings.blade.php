@extends('layouts.admin.app')
@section('title', 'Stranded Settings')

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Home') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Stranded Settings</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">Urban Goodz Stranded</h1>
                <p class="text-muted mb-0">Pricing and dispatch controls. Changes apply to new requests only &mdash; a request keeps the fee it was quoted.</p>
            </div>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.urban-goodz.stranded.settings.update') }}" method="post">
        @csrf

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Help Request Fee</h5>
            </div>
            <div class="card-body">
                <div class="form-group mb-4">
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="help_request_fee_enabled"
                               name="help_request_fee_enabled" value="1" {{ $feeEnabled ? 'checked' : '' }}>
                        <label class="custom-control-label" for="help_request_fee_enabled">
                            Charge a Help Request Fee
                        </label>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Switched off, customers raise Stranded requests at no charge and no fee line is shown in the app.
                    </small>
                </div>

                <div class="row">
                    <div class="col-md-6 form-group">
                        <label for="help_request_fee">Standard fee ($)</label>
                        <input type="number" step="0.01" min="0" max="1000" class="form-control"
                               id="help_request_fee" name="help_request_fee"
                               value="{{ old('help_request_fee', number_format($feeMinor / 100, 2, '.', '')) }}" required>
                        <small class="text-muted">Belongs entirely to Urban Goodz. Non-refundable once the request has been broadcast.</small>
                    </div>

                    <div class="col-md-6 form-group">
                        <label for="member_help_request_fee">Urban Goodz+ member fee ($)</label>
                        <input type="number" step="0.01" min="0" max="1000" class="form-control"
                               id="member_help_request_fee" name="member_help_request_fee"
                               value="{{ old('member_help_request_fee', number_format($memberFeeMinor / 100, 2, '.', '')) }}" required>
                        <small class="text-muted">Cannot exceed the standard fee.</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Revenue</h5>
            </div>
            <div class="card-body row">
                <div class="col-md-6 form-group">
                    <label for="priority_upgrade">Priority Rescue upgrade ($)</label>
                    <input type="number" step="0.01" min="0" max="1000" class="form-control"
                           id="priority_upgrade" name="priority_upgrade"
                           value="{{ old('priority_upgrade', number_format($priorityUpgradeMinor / 100, 2, '.', '')) }}" required>
                    <small class="text-muted">Optional paid upgrade for faster dispatch.</small>
                </div>

                <div class="col-md-6 form-group">
                    <label for="provider_commission_percent">Professional provider commission (%)</label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control"
                           id="provider_commission_percent" name="provider_commission_percent"
                           value="{{ old('provider_commission_percent', number_format($providerCommissionBps / 100, 2, '.', '')) }}" required>
                    <small class="text-muted">Taken from the professional provider's price, not from Samaritan rewards.</small>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Dispatch</h5>
            </div>
            <div class="card-body row">
                <div class="col-md-4 form-group">
                    <label for="radius_ladder">Broadcast radius ladder (miles)</label>
                    <input type="text" class="form-control" id="radius_ladder" name="radius_ladder"
                           value="{{ old('radius_ladder', $radiusLadder) }}" required>
                    <small class="text-muted">Comma separated. The search widens through these until help is found.</small>
                </div>

                <div class="col-md-4 form-group">
                    <label for="offer_ttl_seconds">Responder answer window (seconds)</label>
                    <input type="number" min="5" max="600" class="form-control"
                           id="offer_ttl_seconds" name="offer_ttl_seconds"
                           value="{{ old('offer_ttl_seconds', $offerTtlSeconds) }}" required>
                </div>

                <div class="col-md-4 form-group">
                    <label for="escalation_minutes">Escalate to professionals after (minutes)</label>
                    <input type="number" min="1" max="1440" class="form-control"
                           id="escalation_minutes" name="escalation_minutes"
                           value="{{ old('escalation_minutes', $escalationMinutes) }}" required>
                    <small class="text-muted">How long Goodz Samaritans get before professional providers are offered.</small>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save settings</button>
    </form>
</div>
@endsection
