@extends('layouts.admin.app')

@section('title', translate('Dedicated Routes'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Dedicated Routes') }}</h1>
            <a href="{{ route('admin.urban-goodz.dedicated-routes.create') }}" class="btn btn-primary">
                <i class="tio-add"></i> {{ translate('Create Route') }}
            </a>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('#') }}</th>
                                <th>{{ translate('Route Name') }}</th>
                                <th>{{ translate('Client') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Packages') }}</th>
                                <th>{{ translate('Progress') }}</th>
                                <th>{{ translate('Driver') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($routes as $key => $route)
                                <tr>
                                    <td>{{ $routes->firstItem() + $key }}</td>
                                    <td>
                                        <a href="{{ route('admin.urban-goodz.dedicated-routes.show', $route->id) }}" class="text-primary fw-semibold">
                                            {{ $route->route_name }}
                                        </a>
                                    </td>
                                    <td>{{ $route->client?->company_name ?? 'N/A' }}</td>
                                    <td><span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $route->route_type)) }}</span></td>
                                    <td>
                                        {{ $route->completed_packages }}/{{ $route->total_packages }}
                                        @if($route->failed_packages > 0)
                                            <span class="text-danger">({{ $route->failed_packages }} failed)</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-1">
                                            <div class="progress" style="height: 6px; width: 80px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $route->progressPercent() }}%"></div>
                                            </div>
                                            <small>{{ $route->progressPercent() }}%</small>
                                        </div>
                                    </td>
                                    <td>{{ $route->driver?->f_name ?? 'Unassigned' }}</td>
                                    <td>
                                        @php
                                            $statusMap = ['pending' => 'secondary', 'active' => 'info', 'in_progress' => 'warning', 'completed' => 'success', 'canceled' => 'danger'];
                                        @endphp
                                        <span class="badge badge-soft-{{ $statusMap[$route->status] ?? 'secondary' }}">
                                            {{ ucwords(str_replace('_', ' ', $route->status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $route->scheduled_date?->format('M d, Y') ?? '—' }}</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('admin.urban-goodz.dedicated-routes.show', $route->id) }}" class="btn btn-sm btn-outline-info" title="{{ translate('View') }}">
                                                <i class="tio-visible"></i>
                                            </a>
                                            <a href="{{ route('admin.urban-goodz.dedicated-routes.packages', $route->id) }}" class="btn btn-sm btn-outline-primary" title="{{ translate('Packages') }}">
                                                <i class="tio-parcel"></i>
                                            </a>
                                            <a href="{{ route('admin.urban-goodz.dedicated-routes.report', $route->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ translate('Report') }}">
                                                <i class="tio-document"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center py-4">{{ translate('No dedicated routes found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer">
                {{ $routes->links() }}
            </div>
        </div>
    </div>
@endsection
