@extends('layouts.admin.app')

@section('title', translate('Business Types'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Business Types') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $types->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.business-types.create') }}" class="btn btn--primary">
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
                                   placeholder="{{ translate('Search by name or slug') }}"
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
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Slug') }}</th>
                            <th>{{ translate('Icon') }}</th>
                            <th>{{ translate('Sort Order') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $key => $type)
                            <tr>
                                <td>{{ $types->firstItem() + $key }}</td>
                                <td>{{ $type->name }}</td>
                                <td><code>{{ $type->slug }}</code></td>
                                <td>{{ $type->icon ?? '-' }}</td>
                                <td>{{ $type->sort_order }}</td>
                                <td>
                                    <label class="toggle-switch my-0">
                                        <input type="checkbox" class="toggle-switch-input"
                                               onchange="location.href='{{ route('admin.urban-goodz.business-types.status', [$type->id, $type->is_active ? 0 : 1]) }}'"
                                               {{ $type->is_active ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-types.edit', $type->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-types.mapping', $type->id) }}">
                                                <i class="tio-link"></i> {{ translate('Map Capabilities') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.business-types.destroy', $type->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this business type?') }}')">
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

            @if($types->hasPages())
                <div class="card-footer">
                    {{ $types->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
