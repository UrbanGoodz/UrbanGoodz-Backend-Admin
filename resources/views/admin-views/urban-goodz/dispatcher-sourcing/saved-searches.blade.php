@extends('layouts.admin.app')

@section('title', translate('Dispatcher Load Sourcing — Saved Searches'))

@section('content')
    <div class="content container-fluid">

        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Dashboard') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.saved-searches') }}" class="btn btn--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.dispatcher-sourcing.best-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Assignments') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.index') }}">{{ translate('Dispatcher') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.dispatcher-sourcing.dashboard') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Saved Searches') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Saved Searches') }}</h1>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="tio-save mr-1"></i> {{ translate('Create New Saved Search') }}</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.save-search') }}" class="row g-3">
                    @csrf
                    <div class="col-md-3 col-6">
                        <label class="form-label fw-bold">{{ translate('Search Name') }}</label>
                        <input type="text" name="name" class="form-control form-control-sm" placeholder="{{ translate('e.g. TX to CA Reefer') }}" required>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Origin State') }}</label>
                        <input type="text" name="criteria[origin_state]" class="form-control form-control-sm" placeholder="e.g. TX">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Destination State') }}</label>
                        <input type="text" name="criteria[destination_state]" class="form-control form-control-sm" placeholder="e.g. CA">
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Equipment Type') }}</label>
                        <select name="criteria[equipment_type]" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            <option value="dry_van">{{ translate('Dry Van') }}</option>
                            <option value="reefer">{{ translate('Reefer') }}</option>
                            <option value="flatbed">{{ translate('Flatbed') }}</option>
                            <option value="box_truck">{{ translate('Box Truck') }}</option>
                            <option value="power_only">{{ translate('Power Only') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-6">
                        <label class="form-label fw-bold">{{ translate('Min Rate ($)') }}</label>
                        <input type="number" step="50" name="criteria[min_rate]" class="form-control form-control-sm" placeholder="500">
                    </div>
                    <div class="col-md-1 col-6">
                        <label class="form-label fw-bold">{{ translate('Auto Alert') }}</label>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="auto_alert" value="1" id="autoAlert">
                            <label class="form-check-label" for="autoAlert">{{ translate('On') }}</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn--primary btn-sm">
                            <i class="tio-save"></i> {{ translate('Save Search') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Name') }}</th>
                                <th>{{ translate('Criteria Summary') }}</th>
                                <th class="text-center">{{ translate('Auto-Alert') }}</th>
                                <th class="text-center">{{ translate('Last Run Count') }}</th>
                                <th>{{ translate('Last Run At') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($savedSearches as $search)
                            <tr>
                                <td><strong>{{ $search->name }}</strong></td>
                                <td>
                                    <small class="text-muted">
                                        @if(is_array($search->criteria))
                                            @if(!empty($search->criteria['origin_state']))
                                                {{ translate('Origin') }}: {{ $search->criteria['origin_state'] }}
                                            @endif
                                            @if(!empty($search->criteria['destination_state']))
                                                | {{ translate('Dest') }}: {{ $search->criteria['destination_state'] }}
                                            @endif
                                            @if(!empty($search->criteria['equipment_type']))
                                                | {{ ucwords(str_replace('_', ' ', $search->criteria['equipment_type'])) }}
                                            @endif
                                            @if(!empty($search->criteria['min_rate']))
                                                | ${{ number_format($search->criteria['min_rate']) }}+ {{ translate('min') }}
                                            @endif
                                        @else
                                            {{ translate('All loads') }}
                                        @endif
                                    </small>
                                </td>
                                <td class="text-center">
                                    @if($search->auto_alert)
                                        <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <strong>{{ $search->last_run_result_count ?? 0 }}</strong>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $search->last_run_at ? $search->last_run_at->diffForHumans() : translate('Never') }}</small>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.run-search', $search->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn--primary" title="{{ translate('Run') }}">
                                                <i class="tio-play"></i>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.dispatcher-sourcing.delete-search', $search->id) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Delete') }}" onclick="return confirm('{{ translate('Are you sure?') }}')">
                                                <i class="tio-delete"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    {{ translate('No saved searches yet. Use the search form above to create one.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
