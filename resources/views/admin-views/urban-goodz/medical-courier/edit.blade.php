@extends('layouts.admin.master')
@section('title', 'Edit Job — ' . $job->job_number)

@section('content')
<div class="container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Home') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.medical-courier.index') }}">Medical Courier</a></li>
                        <li class="breadcrumb-item active">{{ $job->job_number }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">Edit {{ $job->job_number }}</h1>
            </div>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.urban-goodz.medical-courier.update', $job->id) }}">
        @csrf @method('PUT')
        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Pickup -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title text-success">Pickup Location</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Facility Name</label><input type="text" name="pickup_facility_name" class="form-control" value="{{ old('pickup_facility_name', $job->pickup_facility_name) }}"></div>
                            <div class="col-md-6"><label class="form-label">Address *</label><input type="text" name="pickup_location" class="form-control" value="{{ old('pickup_location', $job->pickup_location) }}" required></div>
                            <div class="col-md-4"><label class="form-label">Contact Name</label><input type="text" name="pickup_contact_name" class="form-control" value="{{ old('pickup_contact_name', $job->pickup_contact_name) }}"></div>
                            <div class="col-md-4"><label class="form-label">Contact Phone</label><input type="text" name="pickup_contact_phone" class="form-control" value="{{ old('pickup_contact_phone', $job->pickup_contact_phone) }}"></div>
                            <div class="col-md-2"><label class="form-label">Lat</label><input type="number" step="any" name="pickup_lat" class="form-control" value="{{ old('pickup_lat', $job->pickup_lat) }}"></div>
                            <div class="col-md-2"><label class="form-label">Lng</label><input type="number" step="any" name="pickup_lng" class="form-control" value="{{ old('pickup_lng', $job->pickup_lng) }}"></div>
                            <div class="col-md-6"><label class="form-label">Window Start</label><input type="datetime-local" name="pickup_window_start" class="form-control" value="{{ old('pickup_window_start', $job->pickup_window_start?->format('Y-m-d\TH:i')) }}"></div>
                            <div class="col-md-6"><label class="form-label">Window End</label><input type="datetime-local" name="pickup_window_end" class="form-control" value="{{ old('pickup_window_end', $job->pickup_window_end?->format('Y-m-d\TH:i')) }}"></div>
                        </div>
                    </div>
                </div>

                <!-- Delivery -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title text-danger">Delivery Location</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Facility Name</label><input type="text" name="delivery_facility_name" class="form-control" value="{{ old('delivery_facility_name', $job->delivery_facility_name) }}"></div>
                            <div class="col-md-6"><label class="form-label">Address *</label><input type="text" name="delivery_location" class="form-control" value="{{ old('delivery_location', $job->delivery_location) }}" required></div>
                            <div class="col-md-4"><label class="form-label">Contact Name</label><input type="text" name="delivery_contact_name" class="form-control" value="{{ old('delivery_contact_name', $job->delivery_contact_name) }}"></div>
                            <div class="col-md-4"><label class="form-label">Contact Phone</label><input type="text" name="delivery_contact_phone" class="form-control" value="{{ old('delivery_contact_phone', $job->delivery_contact_phone) }}"></div>
                            <div class="col-md-2"><label class="form-label">Lat</label><input type="number" step="any" name="delivery_lat" class="form-control" value="{{ old('delivery_lat', $job->delivery_lat) }}"></div>
                            <div class="col-md-2"><label class="form-label">Lng</label><input type="number" step="any" name="delivery_lng" class="form-control" value="{{ old('delivery_lng', $job->delivery_lng) }}"></div>
                            <div class="col-md-6"><label class="form-label">Window Start</label><input type="datetime-local" name="delivery_window_start" class="form-control" value="{{ old('delivery_window_start', $job->delivery_window_start?->format('Y-m-d\TH:i')) }}"></div>
                            <div class="col-md-6"><label class="form-label">Window End</label><input type="datetime-local" name="delivery_window_end" class="form-control" value="{{ old('delivery_window_end', $job->delivery_window_end?->format('Y-m-d\TH:i')) }}"></div>
                        </div>
                    </div>
                </div>

                <!-- Notes -->
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title">Notes</h5></div>
                    <div class="card-body">
                        <textarea name="admin_notes" class="form-control" rows="3">{{ old('admin_notes', $job->admin_notes) }}</textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title">Specimen Info</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Specimen Type</label>
                            <select name="specimen_type" class="form-select">
                                <option value="">Select...</option>
                                @foreach(['Blood Samples','Urine Samples','Tissue','Pharmaceutical','Lab Specimens','Organ Transport','Other'] as $t)
                                <option value="{{ $t }}" {{ old('specimen_type', $job->specimen_type) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Count</label><input type="number" name="specimen_count" class="form-control" value="{{ old('specimen_count', $job->specimen_count) }}" min="1"></div>
                        <div class="form-check mb-2"><input type="checkbox" name="requires_refrigeration" value="1" class="form-check-input" {{ old('requires_refrigeration', $job->requires_refrigeration) ? 'checked' : '' }}><label class="form-check-label">Requires Refrigeration</label></div>
                        <div class="form-check mb-0"><input type="checkbox" name="is_biological_hazard" value="1" class="form-check-input" {{ old('is_biological_hazard', $job->is_biological_hazard) ? 'checked' : '' }}><label class="form-check-label">Biological Hazard</label></div>
                        <hr>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label fs-sm">Temp Min °F</label><input type="number" step="any" name="temperature_min_f" class="form-control form-control-sm" value="{{ old('temperature_min_f', $job->temperature_min_f) }}"></div>
                            <div class="col-6"><label class="form-label fs-sm">Temp Max °F</label><input type="number" step="any" name="temperature_max_f" class="form-control form-control-sm" value="{{ old('temperature_max_f', $job->temperature_max_f) }}"></div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title">Job Info</h5></div>
                    <div class="card-body">
                        <div class="mb-3"><label class="form-label">Priority</label>
                            <select name="priority" class="form-select">
                                @foreach(['low','normal','high','urgent'] as $p)
                                <option value="{{ $p }}" {{ old('priority', $job->priority) === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3"><label class="form-label">Distance (miles)</label><input type="number" step="any" name="distance_miles" class="form-control" value="{{ old('distance_miles', $job->distance_miles) }}"></div>
                        <div class="mb-3"><label class="form-label">Payout ($)</label><input type="number" step="0.01" name="payout_amount" class="form-control" value="{{ old('payout_amount', $job->payout_amount) }}"></div>
                        <div class="mb-0"><label class="form-label">Payout Type</label>
                            <select name="payout_type" class="form-select">
                                @foreach(['flat','per_mile','per_specimen'] as $t)
                                <option value="{{ $t }}" {{ old('payout_type', $job->payout_type) === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_',' ',$t)) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">Save Changes</button>
            </div>
        </div>
    </form>
</div>
@endsection
