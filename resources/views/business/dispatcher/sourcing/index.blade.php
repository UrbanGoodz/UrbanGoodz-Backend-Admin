@extends('business.layouts.dispatcher')

@section('title', translate('Load Sourcing'))

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <div>
        <h1 class="page-header-title">{{ translate('Load Sourcing') }}</h1>
        <p class="text-muted mb-0">{{ translate('Approved sources only. Unavailable providers fail closed and no loads are fabricated.') }}</p>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach([
        [translate('Available Loads'), $stats['available_loads'], 'success'],
        [translate('Saved Searches'), $stats['saved_searches'], 'primary'],
        [translate('Pending Recommendations'), $stats['pending_recommendations'], 'warning'],
        [translate('Unavailable Sources'), $stats['unavailable_sources'], 'secondary'],
    ] as [$label, $value, $color])
    <div class="col-md-3 col-6">
        <div class="card h-100 border-left-{{ $color }}">
            <div class="card-body py-3">
                <small class="text-muted">{{ $label }}</small>
                <h3 class="mb-0">{{ $value }}</h3>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if(!empty($searchErrors))
<div class="alert alert-warning">
    <strong>{{ translate('Some sources are unavailable.') }}</strong>
    {{ translate('No loads were fabricated. Available approved sources were searched normally.') }}
    <ul class="mb-0 mt-2">
        @foreach($searchErrors as $error)
        <li>{{ $error['source'] }}: {{ $error['reason'] }}</li>
        @endforeach
    </ul>
</div>
@endif

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ translate('Search Approved Sources') }}</h5></div>
    <div class="card-body">
        <form method="POST" action="{{ route('business.dispatcher.sourcing.search') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <label class="form-label">{{ translate('Origin State') }}</label>
                <input class="form-control" name="origin_state" maxlength="2" value="{{ old('origin_state') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ translate('Destination State') }}</label>
                <input class="form-control" name="destination_state" maxlength="2" value="{{ old('destination_state') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ translate('Equipment') }}</label>
                <input class="form-control" name="equipment_type" value="{{ old('equipment_type') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ translate('Minimum Rate') }}</label>
                <input class="form-control" type="number" min="0" step="0.01" name="min_rate" value="{{ old('min_rate') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">{{ translate('Maximum Deadhead') }}</label>
                <input class="form-control" type="number" min="0" step="0.01" name="max_deadhead" value="{{ old('max_deadhead') }}">
            </div>
            <div class="col-md-2">
                <button class="btn btn--primary w-100" type="submit">{{ translate('Search') }}</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ translate('Provider Health') }}</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ translate('Provider') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Enabled') }}</th><th>{{ translate('Connectivity') }}</th><th>{{ translate('Last Success') }}</th></tr></thead>
            <tbody>
            @forelse($sourceHealth as $source)
                <tr>
                    <td>{{ $source->name }}</td>
                    <td>{{ $source->type }}</td>
                    <td>{{ $source->enabled ? translate('Yes') : translate('No') }}</td>
                    <td>{{ $source->api_status }}</td>
                    <td>{{ $source->last_success_at?->format('Y-m-d H:i') ?? translate('Unavailable') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No sources configured') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">{{ $searchResults ? translate('Search Results') : translate('Available External Loads') }}</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ translate('Source') }}</th><th>{{ translate('Route') }}</th><th>{{ translate('Equipment') }}</th><th>{{ translate('Payout') }}</th><th>{{ translate('Rate/Mile') }}</th></tr></thead>
            <tbody>
            @forelse(($searchResults ?: $availableLoads) as $load)
                <tr>
                    <td>{{ $load->source?->name ?? translate('External') }}</td>
                    <td>
                        {{ collect([$load->origin_city, $load->origin_state])->filter()->implode(', ') }}
                        &rarr;
                        {{ collect([$load->destination_city, $load->destination_state])->filter()->implode(', ') }}
                    </td>
                    <td>{{ $load->equipment_type ?? translate('Unavailable') }}</td>
                    <td>{{ $load->gross_rate !== null ? '$' . number_format($load->gross_rate, 2) : translate('Unavailable') }}</td>
                    <td>{{ $load->rate_per_loaded_mile !== null ? '$' . number_format($load->rate_per_loaded_mile, 2) : translate('Unavailable') }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('No external loads available') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">{{ translate('Saved Searches') }}</h5></div>
            @if($canManageSourcing)
            <div class="card-body border-bottom">
                <form method="POST" action="{{ route('business.dispatcher.sourcing.saved-searches.store') }}" class="d-flex gap-2">
                    @csrf
                    <input class="form-control" name="name" required maxlength="255" placeholder="{{ translate('Search name') }}">
                    <button class="btn btn-outline-primary" type="submit">{{ translate('Save') }}</button>
                </form>
            </div>
            @endif
            <div class="table-responsive">
                <table class="table mb-0">
                    <tbody>
                    @forelse($savedSearches as $search)
                        <tr>
                            <td>{{ $search->name }}<br><small class="text-muted">{{ translate('Last results') }}: {{ $search->last_run_result_count }}</small></td>
                            <td class="text-end">
                                @if($canManageSourcing)
                                <form method="POST" action="{{ route('business.dispatcher.sourcing.saved-searches.run', $search->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary">{{ translate('Run') }}</button>
                                </form>
                                <form method="POST" action="{{ route('business.dispatcher.sourcing.saved-searches.delete', $search->id) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">{{ translate('Delete') }}</button>
                                </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-muted">{{ translate('No saved searches') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0">{{ translate('Recommendations') }}</h5></div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>{{ translate('Load') }}</th><th>{{ translate('Driver') }}</th><th>{{ translate('Score') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                    <tbody>
                    @forelse($recommendations as $recommendation)
                        <tr>
                            <td>{{ $recommendation->externalLoad?->external_id ?? translate('Unavailable') }}</td>
                            <td>{{ $recommendation->driver?->f_name }} {{ $recommendation->driver?->l_name }}</td>
                            <td>{{ $recommendation->score }}</td>
                            <td>{{ $recommendation->status }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">{{ translate('No recommendations') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
