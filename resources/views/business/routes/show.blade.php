@extends('business.layouts.app')

@section('title', $route->route_name)

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="page-header-title">{{ $route->route_name }}</h1>
            <span class="badge badge-soft-{{ $route->status === 'active' ? 'success' : ($route->status === 'in_progress' ? 'info' : ($route->status === 'canceled' ? 'danger' : 'secondary')) }}">
                {{ ucfirst($route->status) }}
            </span>
        </div>
        <div class="d-flex gap-1">
            <a href="{{ route('business.routes.packages', $route->id) }}" class="btn btn-outline--primary">
                {{ translate('Packages') }}
            </a>
            <button type="button" class="btn btn-outline-warning" onclick="$('#optimizeModal').modal('show')">
                <i class="tio-route"></i> {{ translate('Optimize') }}
            </button>
            <a href="{{ route('business.routes.edit', $route->id) }}" class="btn btn-outline-info">
                {{ translate('Edit') }}
            </a>
            @if(!in_array($route->status, ['in_progress', 'completed', 'canceled']))
            <button type="button" class="btn btn-outline-danger" onclick="confirmDelete({{ $route->id }}, '{{ $route->route_name }}')">
                {{ translate('Delete') }}
            </button>
            @endif
            <a href="{{ route('business.routes.index') }}" class="btn btn-secondary">
                {{ translate('Back to Routes') }}
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5>{{ translate('Route Details') }}</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">{{ translate('Type') }}</dt>
                        <dd class="col-sm-7">{{ ucwords(str_replace('_', ' ', $route->route_type)) }}</dd>

                        <dt class="col-sm-5">{{ translate('Pickup Location') }}</dt>
                        <dd class="col-sm-7">{{ $route->pickup_location }}</dd>

                        @if($route->end_location)
                        <dt class="col-sm-5">{{ translate('End Location') }}</dt>
                        <dd class="col-sm-7">
                            {{ $route->end_location }}
                            <small class="text-muted d-block" style="color: #6c757d !important;">{{ translate('Stops sorted furthest-to-closest from here') }}</small>
                        </dd>
                        @endif

                        <dt class="col-sm-5">{{ translate('Scheduled Date') }}</dt>
                        <dd class="col-sm-7">{{ $route->scheduled_date?->format('M d, Y') ?? '-' }}</dd>

                        <dt class="col-sm-5">{{ translate('Total Packages') }}</dt>
                        <dd class="col-sm-7">{{ $route->total_packages ?? 0 }}</dd>

                        <dt class="col-sm-5">{{ translate('Completed') }}</dt>
                        <dd class="col-sm-7">{{ $route->completed_packages ?? 0 }}</dd>

                        @if($route->driver)
                        <dt class="col-sm-5">{{ translate('Assigned Driver') }}</dt>
                        <dd class="col-sm-7">{{ $route->driver->f_name }} {{ $route->driver->l_name }}</dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header"><h5>{{ translate('Progress') }}</h5></div>
                <div class="card-body">
                    @php($progress = $route->progressPercent())
                    <div class="progress mb-3" style="height: 20px;">
                        <div class="progress-bar bg-primary" role="progressbar"
                             style="width: {{ $progress }}%"
                             aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                            {{ $progress }}%
                        </div>
                    </div>
                    <p class="text-muted mb-0">
                        {{ $route->completed_packages ?? 0 }} / {{ $route->total_packages ?? 0 }}
                        {{ translate('packages completed') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if($route->packages->isNotEmpty())
    <div class="card mt-3">
        <div class="card-header"><h5>{{ translate('Drop-off Stops') }}</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Recipient') }}</th>
                            <th>{{ translate('Drop-off Address') }}</th>
                            <th>{{ translate('Phone') }}</th>
                            <th>{{ translate('Notes') }}</th>
                            <th>{{ translate('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($route->packages->sortBy('stop_order') as $i => $package)
                        <tr>
                            <td>{{ $package->stop_order ?: $loop->iteration }}</td>
                            <td>{{ $package->dropoff_name ?? '-' }}</td>
                            <td>{{ $package->dropoff_address ?? '-' }}</td>
                            <td>{{ $package->dropoff_phone ?? '-' }}</td>
                            <td>{{ $package->notes ?? '-' }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $package->status === 'delivered' ? 'success' : ($package->status === 'in_transit' ? 'info' : 'secondary') }}">
                                    {{ ucfirst(str_replace('_', ' ', $package->status ?? 'pending')) }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
@endsection

<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="delete-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Delete Route') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Are you sure you want to delete') }} <strong id="delete-route-name"></strong>?</p>
                    <p class="text-danger mb-0" style="color: #dc3545 !important;">
                        {{ translate('This will permanently remove the route and all its packages. This action cannot be undone.') }}
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ translate('Delete Route') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, name) {
        document.getElementById('delete-route-name').textContent = name;
        document.getElementById('delete-form').action = '{{ url('business/routes') }}/' + id + '/delete';
        $('#deleteModal').modal('show');
    }
</script>

<div class="modal fade" id="optimizeModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('business.routes.optimize', $route->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Optimize Route Stops') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted" style="color: #6c757d !important;">
                        {{ translate('Optimization sorts stops from furthest to closest to your end point, so you work your way back.') }}
                    </p>
                    <div class="form-group">
                        <label class="input-label">{{ translate('Where are you ending?') }}</label>
                        <input type="text" class="form-control" name="end_location" value="{{ $route->end_location ?? '' }}" placeholder="{{ translate('Home, warehouse, or return address') }}">
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Leave blank for basic priority sort') }}</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn--primary">{{ translate('Optimize') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
