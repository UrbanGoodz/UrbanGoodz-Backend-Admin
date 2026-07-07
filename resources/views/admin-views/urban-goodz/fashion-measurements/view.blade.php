@extends('layouts.admin.app')

@section('title', translate('Fashion Fit - Measurement #') . $request['id'])

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        {{ translate('Fashion Fit - Measurement #') . $request['id'] }}
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.fashion-fit.index') }}" class="btn btn--secondary">{{ translate('Back to list') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Measurement Details') }}</h5>
                        <span class="badge badge-soft-{{ $request['status'] === 'accepted' ? 'success' : 'info' }}">
                            {{ str_replace('_', ' ', $request['status']) }}
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Height') }}</label>
                                <p>{{ $request['height'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Chest / Bust') }}</label>
                                <p>{{ $request['chest_bust'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Waist') }}</label>
                                <p>{{ $request['waist'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Hips') }}</label>
                                <p>{{ $request['hips'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Inseam') }}</label>
                                <p>{{ $request['inseam'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Sleeve') }}</label>
                                <p>{{ $request['sleeve'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Shoulder Width') }}</label>
                                <p>{{ $request['shoulder_width'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Neck') }}</label>
                                <p>{{ $request['neck'] ?? translate('N/A') }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Preferred Fit') }}</label>
                                <p>{{ $request['preferred_fit'] ?? translate('Standard') }}</p>
                            </div>
                        </div>

                        <hr>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Item Wanted') }}</label>
                                <p>{{ $request['item_wanted'] }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Budget') }}</label>
                                <p>${{ number_format($request['budget'] ?? 0, 2) }}</p>
                            </div>
                            <div class="col-md-4">
                                <label class="font-weight-bold">{{ translate('Deadline') }}</label>
                                <p>{{ $request['deadline'] ?? translate('N/A') }}</p>
                            </div>
                        </div>

                        @if($request['customer_notes'])
                            <hr>
                            <div>
                                <label class="font-weight-bold">{{ translate('Customer Notes') }}</label>
                                <p>{{ $request['customer_notes'] }}</p>
                            </div>
                        @endif

                        @if($request['stylist_notes'])
                            <hr>
                            <div>
                                <label class="font-weight-bold">{{ translate('Stylist Notes') }}</label>
                                <p>{{ $request['stylist_notes'] }}</p>
                            </div>
                        @endif

                        @if($request['corrected_measurements'])
                            <hr>
                            <div>
                                <label class="font-weight-bold">{{ translate('Corrected Measurements') }}</label>
                                <p>{{ $request['corrected_measurements'] }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                @if($request['front_photo_url'] || $request['side_photo_url'] || $request['back_photo_url'])
                    <div class="card mt-3">
                        <div class="card-header">
                            <h5>{{ translate('Photos') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @if($request['front_photo_url'])
                                    <div class="col-md-4">
                                        <label class="font-weight-bold">{{ translate('Front') }}</label>
                                        <img src="{{ $request['front_photo_url'] }}" class="img-fluid rounded" alt="Front">
                                    </div>
                                @endif
                                @if($request['side_photo_url'])
                                    <div class="col-md-4">
                                        <label class="font-weight-bold">{{ translate('Side') }}</label>
                                        <img src="{{ $request['side_photo_url'] }}" class="img-fluid rounded" alt="Side">
                                    </div>
                                @endif
                                @if($request['back_photo_url'])
                                    <div class="col-md-4">
                                        <label class="font-weight-bold">{{ translate('Back') }}</label>
                                        <img src="{{ $request['back_photo_url'] }}" class="img-fluid rounded" alt="Back">
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ translate('Admin Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.urban-goodz.fashion-fit.update', $request['id']) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="form-group">
                                <label>{{ translate('Status') }}</label>
                                <select name="status" class="form-control">
                                    @foreach(['pending', 'needs_more_info', 'accepted', 'adjusted_by_tailor', 'ready_to_quote', 'completed_review'] as $s)
                                        <option value="{{ $s }}" {{ ($request['status'] ?? '') === $s ? 'selected' : '' }}>{{ str_replace('_', ' ', $s) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Quote Amount') }}</label>
                                <input type="number" step="0.01" name="quote_amount" class="form-control" value="{{ $request['quote_amount'] ?? '' }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Mockup Reference') }}</label>
                                <input type="text" name="mockup_reference" class="form-control" value="{{ $request['mockup_reference'] ?? '' }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Assign Tailor (Vendor ID)') }}</label>
                                <select name="tailor_id" class="form-control">
                                    <option value="">{{ translate('Unassign') }}</option>
                                    @foreach($tailors as $t)
                                        <option value="{{ $t->id }}" {{ ($request['tailor_id'] ?? '') == $t->id ? 'selected' : '' }}>{{ $t->name }} ({{ $t->phone }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Stylist Notes') }}</label>
                                <textarea name="stylist_notes" class="form-control" rows="3">{{ $request['stylist_notes'] ?? '' }}</textarea>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Corrected Measurements') }}</label>
                                <textarea name="corrected_measurements" class="form-control" rows="2">{{ $request['corrected_measurements'] ?? '' }}</textarea>
                            </div>
                            <button type="submit" class="btn btn--primary btn-block">{{ translate('Update') }}</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
