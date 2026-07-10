@extends('business.layouts.app')

@section('title', translate('Locations'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="page-header-title">{{ translate('Locations') }}</h1>
        <a href="{{ route('business.locations.create') }}" class="btn btn--primary">
            <i class="tio-add"></i> {{ translate('Add Location') }}
        </a>
    </div>

    @if($locations->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No locations added yet') }}</h5>
            <p class="text-muted mb-3" style="color: #6c757d !important; max-width: 450px; margin: 0 auto;">
                {{ translate('Locations are your pickup and delivery points. Add a location to get started creating routes.') }}
            </p>
            <a href="{{ route('business.locations.create') }}" class="btn btn--primary">
                {{ translate('Add Your First Location') }}
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
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Address') }}</th>
                            <th>{{ translate('City') }}</th>
                            <th>{{ translate('State') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($locations as $location)
                        <tr>
                            <td>{{ $location->name }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $location->type ?? '-')) }}</td>
                            <td>{{ $location->address ?? '-' }}</td>
                            <td>{{ $location->city ?? '-' }}</td>
                            <td>{{ $location->state ?? '-' }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $location->is_active ? 'success' : 'danger' }}">
                                    {{ $location->is_active ? translate('Active') : translate('Inactive') }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('business.locations.edit', $location->id) }}" class="btn btn-sm btn-outline-primary" title="{{ translate('Edit') }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <form action="{{ route('business.locations.deactivate', $location->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Are you sure you want to toggle this location status?') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $location->is_active ? 'warning' : 'success' }}" title="{{ $location->is_active ? translate('Deactivate') : translate('Activate') }}">
                                            <i class="tio-{{ $location->is_active ? 'power_off' : 'power_on' }}"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                {{ translate('No locations found.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($locations->hasPages())
        <div class="card-footer">
            {{ $locations->links() }}
        </div>
        @endif
    </div>
    @endif
@endsection
