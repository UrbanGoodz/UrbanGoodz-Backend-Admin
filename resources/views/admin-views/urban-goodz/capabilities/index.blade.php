@extends('layouts.admin.app')

@section('title', translate('Capabilities'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Capabilities') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $capabilities->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.capabilities.create') }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add New') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search by name or slug') }}"
                                   value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <div class="dropdown mx-2">
                        <select name="group" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Groups') }}</option>
                            @foreach($groups as $g)
                                <option value="{{ $g }}" {{ request('group') === $g ? 'selected' : '' }}>{{ $g ?: 'Ungrouped' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Slug') }}</th>
                            <th>{{ translate('Group') }}</th>
                            <th>{{ translate('Section Key') }}</th>
                            <th>{{ translate('Core') }}</th>
                            <th>{{ translate('Sort Order') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($capabilities as $key => $cap)
                            <tr>
                                <td>{{ $capabilities->firstItem() + $key }}</td>
                                <td>{{ $cap->name }}</td>
                                <td><code>{{ $cap->slug }}</code></td>
                                <td><span class="badge badge-soft-info">{{ $cap->group ?: '-' }}</span></td>
                                <td>{{ $cap->admin_section_key ?: '-' }}</td>
                                <td>
                                    @if($cap->is_core)
                                        <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                    @endif
                                </td>
                                <td>{{ $cap->sort_order }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.capabilities.edit', $cap->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.capabilities.destroy', $cap->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this capability?') }}')">
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

            @if($capabilities->hasPages())
                <div class="card-footer">
                    {{ $capabilities->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
