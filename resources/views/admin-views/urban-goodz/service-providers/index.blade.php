@extends('layouts.admin.app')

@section('title', translate('Service Providers'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Service Providers') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $providers->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.service-providers.create') }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add New') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper justify-content-end">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search by name, contact, or email') }}"
                                   value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Business Name') }}</th>
                            <th>{{ translate('Contact') }}</th>
                            <th>{{ translate('Category') }}</th>
                            <th>{{ translate('Verified') }}</th>
                            <th>{{ translate('Active') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($providers as $key => $provider)
                            <tr>
                                <td>{{ $providers->firstItem() + $key }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.service-providers.show', $provider->id) }}" class="text--primary">
                                        {{ $provider->business_name }}
                                    </a>
                                </td>
                                <td>
                                    <div>{{ $provider->contact_name ?? '-' }}</div>
                                    <small class="text-muted" style="color: #6c757d !important;">{{ $provider->email ?? '-' }}</small>
                                </td>
                                <td>{{ $provider->service_category ?? '-' }}</td>
                                <td>
                                    <label class="toggle-switch my-0">
                                        <input type="checkbox" class="toggle-switch-input"
                                               onchange="location.href='{{ route('admin.urban-goodz.service-providers.status', [$provider->id, $provider->is_verified ? 0 : 1]) }}'"
                                               {{ $provider->is_verified ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <label class="toggle-switch my-0">
                                        <input type="checkbox" class="toggle-switch-input"
                                               onchange="location.href='{{ route('admin.urban-goodz.service-providers.status', [$provider->id, $provider->is_active ? 0 : 1]) }}'"
                                               {{ $provider->is_active ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.service-providers.show', $provider->id) }}">
                                                <i class="tio-eye"></i> {{ translate('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.service-providers.edit', $provider->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.service-providers.destroy', $provider->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this provider?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item">
                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($providers->hasPages())
                <div class="card-footer">
                    {{ $providers->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
