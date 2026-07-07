@extends('layouts.admin.app')

@section('title', translate('Rentals'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Rentals Dashboard') }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.assets.create') }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add Asset') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row gx-2 gx-lg-3">
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-5">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block font-size-sm text-body">{{ translate('Total Assets') }}</span>
                                <span class="card-title font-size-lg">{{ $totalAssets }}</span>
                            </div>
                            <i class="tio-car text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-5">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block font-size-sm text-body">{{ translate('Available') }}</span>
                                <span class="card-title font-size-lg text-success">{{ $availableAssets }}</span>
                            </div>
                            <i class="tio-checkmark-circle text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-5">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block font-size-sm text-body">{{ translate('Active Bookings') }}</span>
                                <span class="card-title font-size-lg text-info">{{ $activeBookings }}</span>
                            </div>
                            <i class="tio-calendar text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3 mb-lg-5">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="d-block font-size-sm text-body">{{ translate('Pending') }}</span>
                                <span class="card-title font-size-lg text-warning">{{ $pendingBookings }}</span>
                            </div>
                            <i class="tio-time text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-3 mb-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Quick Actions') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-6">
                                <a href="{{ route('admin.urban-goodz.rentals.assets.index') }}" class="btn btn--primary btn-block">
                                    <i class="tio-car"></i> {{ translate('All Assets') }}
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('admin.urban-goodz.rentals.bookings.index') }}" class="btn btn-info btn-block">
                                    <i class="tio-calendar"></i> {{ translate('Bookings') }}
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('admin.urban-goodz.rentals.inspections.index') }}" class="btn btn-warning btn-block">
                                    <i class="tio-search"></i> {{ translate('Inspections') }}
                                </a>
                            </div>
                            <div class="col-6">
                                <a href="{{ route('admin.urban-goodz.rentals.assets.create') }}" class="btn btn-success btn-block">
                                    <i class="tio-add"></i> {{ translate('New Asset') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Rental Types') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            @foreach($businessTypes as $slug => $name)
                                <div class="col-6">
                                    <a href="{{ route('admin.urban-goodz.rentals.by-type', $slug) }}" class="btn btn-outline-primary btn-block">
                                        <i class="tio-car"></i> {{ $name }}
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3 mb-lg-5">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Recent Assets') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-borderless table-align-middle mb-0">
                            <thead class="thead-light">
                                <tr><th>{{ translate('Title') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Status') }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse($recentAssets as $a)
                                    <tr>
                                        <td>{{ $a->title }}</td>
                                        <td>{{ $a->asset_type }}</td>
                                        <td><span class="badge badge-soft-{{ $a->status === 'available' ? 'success' : 'warning' }}">{{ $a->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">{{ translate('No assets yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('Recent Bookings') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-borderless table-align-middle mb-0">
                            <thead class="thead-light">
                                <tr><th>{{ translate('Customer') }}</th><th>{{ translate('Asset') }}</th><th>{{ translate('Status') }}</th></tr>
                            </thead>
                            <tbody>
                                @forelse($recentBookings as $b)
                                    <tr>
                                        <td>{{ $b->customer_name ?? '#' . $b->id }}</td>
                                        <td>{{ $b->asset->title ?? '-' }}</td>
                                        <td><span class="badge badge-soft-{{ $b->status === 'approved' ? 'success' : ($b->status === 'pending' ? 'warning' : 'secondary') }}">{{ $b->status }}</span></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-center text-muted">{{ translate('No bookings yet.') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
