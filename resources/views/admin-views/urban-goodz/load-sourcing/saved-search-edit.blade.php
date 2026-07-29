@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Edit Saved Search'))

@section('content')
    <div class="content container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}">{{ translate('Saved Searches') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Edit') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Edit Saved Search') }}</h1>
            </div>
            <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-back-ui"></i> {{ translate('Back') }}
            </a>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif

        @php $criteria = $savedSearch->criteria ?? []; @endphp

        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.update-saved-search', $savedSearch->id) }}">
            @csrf
            @method('PUT')

            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('Search') }}</h5></div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label class="input-label">{{ translate('Name') }}</label>
                        <input type="text" name="name" class="form-control" required
                               value="{{ old('name', $savedSearch->name) }}">
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label class="input-label">{{ translate('Origin City') }}</label>
                            <input type="text" name="origin_city" class="form-control"
                                   value="{{ old('origin_city', $criteria['origin_city'] ?? '') }}">
                        </div>
                        <div class="col-md-2 form-group mb-3">
                            <label class="input-label">{{ translate('Origin State') }}</label>
                            <input type="text" name="origin_state" class="form-control"
                                   value="{{ old('origin_state', $criteria['origin_state'] ?? '') }}">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="input-label">{{ translate('Destination City') }}</label>
                            <input type="text" name="destination_city" class="form-control"
                                   value="{{ old('destination_city', $criteria['destination_city'] ?? '') }}">
                        </div>
                        <div class="col-md-2 form-group mb-3">
                            <label class="input-label">{{ translate('Destination State') }}</label>
                            <input type="text" name="destination_state" class="form-control"
                                   value="{{ old('destination_state', $criteria['destination_state'] ?? '') }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label class="input-label">{{ translate('Equipment Type') }}</label>
                            <input type="text" name="equipment_type" class="form-control"
                                   value="{{ old('equipment_type', $criteria['equipment_type'] ?? '') }}">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="input-label">{{ translate('Minimum Rate') }}</label>
                            <input type="number" step="0.01" name="min_rate" class="form-control"
                                   value="{{ old('min_rate', $criteria['min_rate'] ?? '') }}">
                        </div>
                        <div class="col-md-4 form-group mb-3">
                            <label class="input-label">{{ translate('Maximum Rate') }}</label>
                            <input type="number" step="0.01" name="max_rate" class="form-control"
                                   value="{{ old('max_rate', $criteria['max_rate'] ?? '') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('Alerts') }}</h5></div>
                <div class="card-body">
                    <div class="form-group mb-3">
                        <label class="d-flex align-items-center gap-2">
                            <input type="hidden" name="auto_alert" value="0">
                            <input type="checkbox" name="auto_alert" value="1"
                                   {{ old('auto_alert', $savedSearch->auto_alert) ? 'checked' : '' }}>
                            <span>{{ translate('Send automatic alerts for matching loads') }}</span>
                        </label>
                    </div>
                    <div class="form-group mb-0 col-md-4 px-0">
                        <label class="input-label">{{ translate('Alert Threshold Score') }}</label>
                        <input type="number" name="alert_threshold_score" class="form-control" min="0" max="100"
                               value="{{ old('alert_threshold_score', $savedSearch->alert_threshold_score) }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn--primary">
                <i class="tio-save"></i> {{ translate('Save Changes') }}
            </button>
        </form>

    </div>
@endsection
