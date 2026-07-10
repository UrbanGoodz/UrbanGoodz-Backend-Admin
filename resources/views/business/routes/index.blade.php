@extends('business.layouts.app')

@section('title', translate('Routes'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ translate('Courier Routes') }}</h1>
        <a href="{{ route('business.routes.create') }}" class="btn btn--primary">
            {{ translate('Create Route') }}
        </a>
    </div>

    @if($routes->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No routes submitted yet') }}</h5>
            <p class="text-muted mb-3" style="color: #6c757d !important; max-width: 450px; margin: 0 auto 1rem;">
                {{ translate('Create a route to schedule pickups and deliveries for your business. Routes can be one-time or recurring.') }}
            </p>
            <a href="{{ route('business.routes.create') }}" class="btn btn--primary">
                {{ translate('Create Your First Route') }}
            </a>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Route Name') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Pickup Location') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Stops') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($routes as $route)
                        <tr>
                            <td>{{ $route->route_name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $route->route_type)) }}</td>
                            <td>{{ $route->pickup_location }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $route->status === 'active' ? 'success' : ($route->status === 'in_progress' ? 'info' : ($route->status === 'canceled' ? 'danger' : 'secondary')) }}">
                                    {{ ucfirst($route->status) }}
                                </span>
                            </td>
                            <td>{{ $route->packages_count ?? $route->packages()->count() }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('business.routes.show', $route->id) }}" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                    <a href="{{ route('business.routes.edit', $route->id) }}" class="btn btn-sm btn-outline-info">
                                        {{ translate('Edit') }}
                                    </a>
                                    <a href="{{ route('business.routes.packages', $route->id) }}" class="btn btn-sm btn-outline--primary">
                                        {{ translate('Packages') }}
                                    </a>
                                    @if(!in_array($route->status, ['in_progress', 'completed', 'canceled']))
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmDelete({{ $route->id }}, '{{ $route->route_name }}')">
                                        {{ translate('Delete') }}
                                    </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                {{ translate('No routes found.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($routes->hasPages())
        <div class="card-footer">
            {{ $routes->links() }}
        </div>
        @endif
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

@push('script')
<script>
    function confirmDelete(id, name) {
        document.getElementById('delete-route-name').textContent = name;
        document.getElementById('delete-form').action = '{{ url('business/routes') }}/' + id + '/delete';
        $('#deleteModal').modal('show');
    }
</script>
@endpush
