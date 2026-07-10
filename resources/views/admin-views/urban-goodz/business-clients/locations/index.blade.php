@extends('layouts.admin.app')

@section('title', $client->company_name . ' - ' . translate('Locations'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title">{{ $client->company_name }} {{ translate('Locations') }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.business-clients.locations.create', $client->id) }}" class="btn btn--primary">
                    <i class="tio-add"></i> {{ translate('Add Location') }}
                </a>
                <a href="{{ route('admin.urban-goodz.business-clients.show', $client->id) }}" class="btn btn--secondary">
                    <i class="tio-back"></i> {{ translate('Back to Client') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Address') }}</th>
                            <th>{{ translate('City') }}</th>
                            <th>{{ translate('Contact') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($locations as $key => $loc)
                            <tr>
                                <td>{{ $locations->firstItem() + $key }}</td>
                                <td class="font-weight-bold">{{ $loc->name }}</td>
                                <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', $loc->type) }}</span></td>
                                <td>{{ \Illuminate\Support\Str::limit($loc->address, 40) }}</td>
                                <td>{{ $loc->city }}, {{ $loc->state }}</td>
                                <td>{{ $loc->contact_name ?? translate('N/A') }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $loc->is_active ? 'success' : 'secondary' }}">{{ $loc->is_active ? translate('Active') : translate('Inactive') }}</span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-clients.locations.edit', [$client->id, $loc->id]) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.business-clients.locations.destroy', [$client->id, $loc->id]) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this location?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($locations) === 0)
                            <tr><td colspan="8" class="text-center text-muted">{{ translate('No locations found') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $locations->links() }}</div>
        </div>
    </div>
@endsection
