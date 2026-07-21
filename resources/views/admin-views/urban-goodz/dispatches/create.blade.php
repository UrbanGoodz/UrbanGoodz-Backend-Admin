@extends('layouts.admin.app')

@section('title', translate('Create AI Dispatch'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.dispatches.index') }}">{{ translate('AI Dispatches') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Create') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Create AI Dispatch') }}</h1>
            </div>
            <a href="{{ route('admin.urban-goodz.dispatches.index') }}" class="btn btn-secondary">
                <i class="tio-back"></i> {{ translate('Back') }}
            </a>
        </div>

        <form action="{{ route('admin.urban-goodz.dispatches.store') }}" method="POST">
            @csrf
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Load (Optional)') }}</label>
                            <select name="load_id" class="form-control">
                                <option value="">{{ translate('Select Load (optional)') }}</option>
                                @foreach($loads as $load)
                                    <option value="{{ $load->id }}" @selected(old('load_id') == $load->id)>
                                        {{ $load->reference_number ?? '#' . $load->id }}
                                        — {{ $load->origin_city }}, {{ $load->origin_state }} &rarr; {{ $load->destination_city }}, {{ $load->destination_state }}
                                        @if($load->payout_amount)
                                            (${{ number_format($load->payout_amount, 2) }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">{{ translate('Link to a load board entry if applicable.') }}</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Driver') }} <span class="text-danger">*</span></label>
                            <select name="driver_id" class="form-control" required>
                                <option value="">{{ translate('Select Driver') }}</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" @selected(old('driver_id') == $driver->id)>
                                        {{ $driver->f_name . ' ' . $driver->l_name }}
                                        @if($driver->phone)
                                            ({{ $driver->phone }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Offer Expiration') }} <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="offer_expires_at" class="form-control" required value="{{ old('offer_expires_at') }}">
                            <small class="form-text text-muted">{{ translate('When the offer to the driver expires.') }}</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Driver Payout Amount ($)') }}</label>
                            <input type="number" name="driver_payout_amount" class="form-control" step="0.01" min="0" placeholder="0.00" value="{{ old('driver_payout_amount') }}">
                            <small class="form-text text-muted">{{ translate('Guaranteed payout for the driver upon acceptance.') }}</small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">{{ translate('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="{{ translate('Any special instructions or notes for this dispatch...') }}">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="tio-send"></i> {{ translate('Create Dispatch') }}
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
