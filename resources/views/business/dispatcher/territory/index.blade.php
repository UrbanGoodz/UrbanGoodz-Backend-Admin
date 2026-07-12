@extends('business.layouts.dispatcher')
@section('title', translate('Territory Management'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Territory Management') }}</h1>
</div>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">{{ translate('Assigned States') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('dispatcher.territory.update') }}">
            @csrf
            <p class="text-muted mb-3">{{ translate('Select the states where your dispatch company operates. Loads originating or terminating in these states will be visible to your team.') }}</p>

            <div class="row g-2 mb-3">
                @foreach($allStates as $state)
                <div class="col-md-2 col-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="territory_states[]" value="{{ $state }}" id="state_{{ $state }}" {{ in_array($state, $assignedStates) ? 'checked' : '' }}>
                        <label class="form-check-label" for="state_{{ $state }}">{{ $state }}</label>
                    </div>
                </div>
                @endforeach
            </div>

            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Save Territory') }}</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">{{ translate('Custom Corridors') }}</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('dispatcher.territory.update') }}">
            @csrf
            <p class="text-muted mb-3">{{ translate('Define specific corridors (e.g., TX-LA, LA-MS). These are used for route-based filtering.') }}</p>

            <div class="form-group mb-3">
                <input type="text" name="territory_corridors[]" class="form-control mb-2" placeholder="e.g., TX-LA" value="{{ $corridors[0] ?? '' }}">
                <input type="text" name="territory_corridors[]" class="form-control mb-2" placeholder="e.g., LA-MS" value="{{ $corridors[1] ?? '' }}">
                <input type="text" name="territory_corridors[]" class="form-control mb-2" placeholder="e.g., MS-AL" value="{{ $corridors[2] ?? '' }}">
            </div>

            <button type="submit" class="btn btn--primary"><i class="tio-save"></i> {{ translate('Save Corridors') }}</button>
        </form>
    </div>
</div>
@endsection
