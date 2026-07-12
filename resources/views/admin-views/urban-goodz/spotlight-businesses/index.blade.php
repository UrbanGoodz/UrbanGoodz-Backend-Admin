@extends('layouts.admin.app')

@section('title', translate('Spotlight Businesses'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Spotlight Businesses') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $businesses->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.spotlight-businesses.create') }}" class="btn btn--primary">
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
                                   placeholder="{{ translate('Search by name or category') }}"
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
                            <th>{{ translate('Category') }}</th>
                            <th>{{ translate('Featured') }}</th>
                            <th>{{ translate('Featured Until') }}</th>
                            <th>{{ translate('Active') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($businesses as $key => $business)
                            <tr>
                                <td>{{ $businesses->firstItem() + $key }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.spotlight-businesses.show', $business->id) }}" class="text--primary">
                                        {{ $business->business_name }}
                                    </a>
                                </td>
                                <td>{{ $business->category ?? '-' }}</td>
                                <td>
                                    @if($business->is_featured)
                                        <span class="badge badge-soft-warning"><i class="tio-star"></i> {{ translate('Featured') }}</span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $business->featured_until?->format('M d, Y') ?? '-' }}</td>
                                <td>
                                    <label class="toggle-switch my-0">
                                        <input type="checkbox" class="toggle-switch-input"
                                               onchange="location.href='{{ route('admin.urban-goodz.spotlight-businesses.status', [$business->id, $business->is_active ? 0 : 1]) }}'"
                                               {{ $business->is_active ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.spotlight-businesses.show', $business->id) }}">
                                                <i class="tio-eye"></i> {{ translate('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.spotlight-businesses.edit', $business->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.spotlight-businesses.destroy', $business->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this business?') }}')">
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

            @if($businesses->hasPages())
                <div class="card-footer">
                    {{ $businesses->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
