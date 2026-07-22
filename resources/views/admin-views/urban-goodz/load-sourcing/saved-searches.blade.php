@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Saved Searches'))

@section('content')
    <div class="content container-fluid">

        {{-- Sub-Navigation --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Overview') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-link"></i> {{ translate('Sources') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Recommendations') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-refresh"></i> {{ translate('Sync Runs') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-warning"></i> {{ translate('Errors') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-settings-outlined"></i> {{ translate('Settings') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Breadcrumb & Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="#">{{ translate('AI Operations') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Saved Searches') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Saved Searches') }}</h1>
            </div>
        </div>

        {{-- Saved Searches Table --}}
        <div class="card mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Name') }}</th>
                                <th>{{ translate('Created By') }}</th>
                                <th>{{ translate('Criteria Summary') }}</th>
                                <th>{{ translate('Source Filters') }}</th>
                                <th class="text-center">{{ translate('Auto-Alert') }}</th>
                                <th>{{ translate('Alert Threshold') }}</th>
                                <th class="text-center">{{ translate('Last Run Count') }}</th>
                                <th>{{ translate('Last Run At') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($savedSearches as $search)
                            <tr>
                                <td><strong>{{ $search->name }}</strong></td>
                                <td><small>{{ $search->createdBy->name ?? translate('System') }}</small></td>
                                <td>
                                    <small class="text-muted">
                                        @if($search->origin_state)
                                            {{ translate('Origin') }}: {{ $search->origin_state }}
                                        @endif
                                        @if($search->destination_state)
                                            | {{ translate('Dest') }}: {{ $search->destination_state }}
                                        @endif
                                        @if($search->equipment_type)
                                            | {{ ucwords(str_replace('_', ' ', $search->equipment_type)) }}
                                        @endif
                                        @if($search->min_rate)
                                            | ${{ number_format($search->min_rate) }}+ {{ translate('min') }}
                                        @endif
                                    </small>
                                </td>
                                <td>
                                    @if($search->source_filters && count($search->source_filters) > 0)
                                        @foreach($search->source_filters as $sf)
                                            <span class="badge badge-soft-info">{{ ucfirst($sf) }}</span>
                                        @endforeach
                                    @else
                                        <small class="text-muted">{{ translate('All Sources') }}</small>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($search->auto_alert)
                                        <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $search->alert_threshold ?? translate('N/A') }}</small>
                                </td>
                                <td class="text-center">
                                    <strong>{{ $search->last_run_count ?? 0 }}</strong>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $search->last_run_at ? $search->last_run_at->diffForHumans() : translate('Never') }}</small>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.run-saved-search', $search->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn--primary" title="{{ translate('Run') }}">
                                                <i class="tio-play"></i> {{ translate('Run') }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.urban-goodz.load-sourcing.edit-saved-search', $search->id) }}" class="btn btn-sm btn-outline--primary" title="{{ translate('Edit') }}">
                                            <i class="tio-edit"></i> {{ translate('Edit') }}
                                        </a>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.delete-saved-search', $search->id) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Delete') }}" onclick="return confirm('{{ translate('Are you sure you want to delete this saved search?') }}')">
                                                <i class="tio-delete"></i> {{ translate('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    {{ translate('No saved searches yet. Use the Search Loads page to create one.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($savedSearches) && $savedSearches instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="card-footer d-flex justify-content-end">
                {{ $savedSearches->links() }}
            </div>
            @endif
        </div>

    </div>
@endsection
