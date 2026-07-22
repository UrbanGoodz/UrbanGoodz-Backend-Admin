@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Sync Runs'))

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
                    <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Recommendations') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn--primary btn-sm">
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Sync Runs') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Sync Runs') }}</h1>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Source') }}</th>
                                <th>{{ translate('Started') }}</th>
                                <th>{{ translate('Completed') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th class="text-center">{{ translate('Loads Found') }}</th>
                                <th class="text-center">{{ translate('New') }}</th>
                                <th class="text-center">{{ translate('Updated') }}</th>
                                <th class="text-center">{{ translate('Duplicates') }}</th>
                                <th class="text-center">{{ translate('Expired') }}</th>
                                <th>{{ translate('Duration') }}</th>
                                <th class="text-center">{{ translate('Errors') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($syncRuns as $run)
                            @php
                                $statusConfig = match($run->status) {
                                    'running' => ['class' => 'badge-soft-info', 'icon' => 'tio-refresh tio-spin'],
                                    'completed' => ['class' => 'badge-soft-success', 'icon' => 'tio-checkmark-circle'],
                                    'failed' => ['class' => 'badge-soft-danger', 'icon' => 'tio-warning'],
                                    'partial' => ['class' => 'badge-soft-warning', 'icon' => 'tio-alert-circle'],
                                    default => ['class' => 'badge-soft-secondary', 'icon' => 'tio-help-circle'],
                                };
                            @endphp
                            <tr>
                                <td><strong>{{ $run->source->name ?? translate('Unknown') }}</strong></td>
                                <td><small>{{ $run->started_at ? $run->started_at->format('M d, Y H:i:s') : '—' }}</small></td>
                                <td><small>{{ $run->completed_at ? $run->completed_at->format('M d, Y H:i:s') : '—' }}</small></td>
                                <td>
                                    <span class="badge {{ $statusConfig['class'] }}">
                                        <i class="{{ $statusConfig['icon'] }}"></i>
                                        {{ ucfirst($run->status) }}
                                    </span>
                                </td>
                                <td class="text-center"><strong>{{ $run->loads_found ?? 0 }}</strong></td>
                                <td class="text-center"><span class="badge badge-soft-success">{{ $run->new_count ?? 0 }}</span></td>
                                <td class="text-center"><span class="badge badge-soft-info">{{ $run->updated_count ?? 0 }}</span></td>
                                <td class="text-center"><span class="badge badge-soft-warning">{{ $run->duplicates_count ?? 0 }}</span></td>
                                <td class="text-center"><span class="badge badge-soft-secondary">{{ $run->expired_count ?? 0 }}</span></td>
                                <td>
                                    <small class="text-muted">
                                        @if($run->duration_seconds !== null)
                                            {{ sprintf('%dm %ds', floor($run->duration_seconds / 60), $run->duration_seconds % 60) }}
                                        @else
                                            —
                                        @endif
                                    </small>
                                </td>
                                <td class="text-center">
                                    @if(($run->errors_count ?? 0) > 0)
                                        <span class="badge badge-soft-danger">{{ $run->errors_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($run->status === 'failed' || $run->status === 'partial')
                                    <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.retry-sync', $run->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline--primary" title="{{ translate('Retry Sync') }}">
                                            <i class="tio-refresh"></i> {{ translate('Retry') }}
                                        </button>
                                    </form>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">
                                    {{ translate('No sync runs recorded yet.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($syncRuns) && $syncRuns instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="card-footer d-flex justify-content-end">
                {{ $syncRuns->links() }}
            </div>
            @endif
        </div>

    </div>
@endsection
