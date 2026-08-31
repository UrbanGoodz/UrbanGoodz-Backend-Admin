@extends('business.layouts.app')

@section('title', translate('Create Dispatch'))

@push('css_or_js')
<link rel="stylesheet" href="{{ asset('public/assets/admin/css/select2.min.css') }}">
@endpush

@section('content')
    <div class="container-fluid">
        <div class="page-title">
            <h4 class="mb-0">{{ translate('Create New Dispatch') }}</h4>
            <ol class="breadcrumb mb-0 ps-0 pt-1">
                <li class="breadcrumb-item"><a href="{{ route('business.dashboard') }}"><i class="bi-house"></i></a></li>
                <li class="breadcrumb-item"><a href="{{ route('business.ai-logistics.dispatches.index') }}">{{ translate('Dispatches') }}</a></li>
                <li class="breadcrumb-item active">{{ translate('Create') }}</li>
            </ol>
        </div>

        <div class="card mt-3">
            <div class="card-body">
                <form action="{{ route('business.ai-logistics.dispatch.store') }}" method="post">
                    @csrf

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label" for="load_id">{{ translate('Load') }} <small>({{ translate('optional') }})</small></label>
                            <select name="load_id" id="load_id" class="form-control select2">
                                <option value="">{{ translate('-- Select Load --') }}</option>
                                @foreach($loads as $load)
                                    <option value="{{ $load->id }}" {{ old('load_id') == $load->id ? 'selected' : '' }}>
                                        #{{ $load->reference_number }} - {{ $load->origin_city }}, {{ $load->origin_state }} → {{ $load->destination_city }}, {{ $load->destination_state }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label required" for="driver_id">{{ translate('Driver') }}</label>
                            <select name="driver_id" id="driver_id" class="form-control select2" required>
                                <option value="">{{ translate('-- Select Driver --') }}</option>
                                @foreach($drivers as $driver)
                                    <option value="{{ $driver->id }}" {{ old('driver_id') == $driver->id ? 'selected' : '' }}>
                                        {{ $driver->f_name }} {{ $driver->l_name }} ({{ $driver->phone ?? translate('N/A') }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="dispatch_type">{{ translate('Dispatch Type') }}</label>
                            <select name="dispatch_type" id="dispatch_type" class="form-control">
                                <option value="manual">{{ translate('Manual') }}</option>
                                <option value="ai_match">{{ translate('AI Match') }}</option>
                                <option value="copilot_rec">{{ translate('Copilot Recommendation') }}</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="ai_confidence">{{ translate('AI Confidence') }} <small>({{ translate('optional') }})</small></label>
                            <input type="number" step="0.01" min="0" max="100" name="ai_confidence" id="ai_confidence"
                                class="form-control" value="{{ old('ai_confidence') }}" placeholder="e.g. 87.5">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="offer_expires_at">{{ translate('Offer Expires At') }} <small>({{ translate('optional') }})</small></label>
                            <input type="datetime-local" name="offer_expires_at" id="offer_expires_at"
                                class="form-control" value="{{ old('offer_expires_at') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="ai_reasoning">{{ translate('AI Reasoning / Notes') }}</label>
                            <textarea name="ai_reasoning" id="ai_reasoning" class="form-control" rows="3">{{ old('ai_reasoning') }}</textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="{{ route('business.ai-logistics.dispatches.index') }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ translate('Create & Send Dispatch') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script')
<script src="{{ asset('public/assets/admin/js/select2.min.js') }}"></script>
<script>
    $(document).ready(function () {
        $('.select2').select2({ width: '100%' });
    });
</script>
@endpush
