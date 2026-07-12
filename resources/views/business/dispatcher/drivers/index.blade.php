@extends('business.layouts.dispatcher')
@section('title', translate('Available Drivers'))
@section('content')
<h1 class="page-header-title mb-3">{{ translate('Available Drivers') }}</h1>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Name') }}</th>
                        <th>{{ translate('Phone') }}</th>
                        <th>{{ translate('Vehicle') }}</th>
                        <th>{{ translate('Current Orders') }}</th>
                        <th>{{ translate('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drivers as $driver)
                    <tr>
                        <td class="fw-bold">{{ $driver->f_name }} {{ $driver->l_name }}</td>
                        <td>{{ $driver->phone ?? '-' }}</td>
                        <td>{{ ucfirst($driver->vehicle_type ?? '-') }}</td>
                        <td class="text-center">{{ $driver->current_orders ?? 0 }}</td>
                        <td>
                            @if($driver->current_orders < ($driver->dm_maximum_orders ?? 1))
                            <span class="badge badge-soft-success">{{ translate('Available') }}</span>
                            @else
                            <span class="badge badge-soft-warning">{{ translate('Busy') }}</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">{{ translate('No drivers found in your territory') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
