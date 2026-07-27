@extends('business.layouts.dispatcher')

@section('title', translate('Business Routes'))

@section('content')
<div class="page-header"><h1 class="page-header-title">{{ translate('Business Routes') }}</h1></div>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>{{ translate('Route') }}</th><th>{{ translate('Driver') }}</th><th>{{ translate('Optimization') }}</th><th>{{ translate('Stops') }}</th><th></th></tr></thead>
            <tbody>
            @forelse($routes as $route)
                <tr>
                    <td>{{ $route->route_name }}</td>
                    <td>{{ $route->driver ? $route->driver->f_name.' '.$route->driver->l_name : '—' }}</td>
                    <td>{{ ucwords(str_replace('_', ' ', $route->optimization_status ?? 'not_optimized')) }}</td>
                    <td>{{ $route->total_packages }}</td>
                    <td><a href="{{ url('business/dispatcher/routes/'.$route->id) }}" class="btn btn-sm btn-outline--primary">{{ translate('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center">{{ translate('No routes found') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $routes->links() }}</div>
</div>
@endsection
