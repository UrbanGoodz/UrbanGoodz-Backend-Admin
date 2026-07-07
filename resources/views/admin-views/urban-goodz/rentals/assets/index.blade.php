@extends('layouts.admin.app')

@section('title', translate('Rental Assets'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate($typeLabel ?? 'Rental Assets') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $assets->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.rentals.assets.create') }}" class="btn btn--primary">
                        <i class="tio-add"></i> {{ translate('Add Asset') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.rentals.dashboard') }}" class="btn btn--secondary">{{ translate('Dashboard') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header py-1 border-0">
                <div class="search--button-wrapper">
                    <form class="search-form min--260" method="GET">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control h--40px"
                                   placeholder="{{ translate('Search assets') }}" value="{{ request('search') }}">
                            <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                        </div>
                    </form>
                    <select name="asset_type" class="form-control ml-2" style="max-width:160px" onchange="this.form.submit()">
                        <option value="">{{ translate('All Types') }}</option>
                        <option value="car" {{ request('asset_type') === 'car' ? 'selected' : '' }}>Car</option>
                        <option value="motorcycle" {{ request('asset_type') === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                        <option value="scooter" {{ request('asset_type') === 'scooter' ? 'selected' : '' }}>Scooter</option>
                        <option value="equipment" {{ request('asset_type') === 'equipment' ? 'selected' : '' }}>Equipment</option>
                        <option value="tool" {{ request('asset_type') === 'tool' ? 'selected' : '' }}>Tool</option>
                        <option value="other" {{ request('asset_type') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Title') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Make / Model') }}</th>
                            <th>{{ translate('Daily Rate') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Active') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($assets as $key => $asset)
                            <tr>
                                <td>{{ $assets->firstItem() + $key }}</td>
                                <td>{{ $asset->title }}</td>
                                <td><span class="badge badge-soft-info">{{ $asset->asset_type }}</span></td>
                                <td>{{ $asset->make ? $asset->make . ' ' . $asset->model : '-' }}</td>
                                <td>{{ $asset->daily_rate ? '$' . number_format($asset->daily_rate, 2) : '-' }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $asset->status === 'available' ? 'success' : 'warning' }}">
                                        {{ $asset->status }}
                                    </span>
                                </td>
                                <td>
                                    <label class="toggle-switch my-0">
                                        <input type="checkbox" class="toggle-switch-input"
                                               onchange="location.href='{{ route('admin.urban-goodz.rentals.assets.status', [$asset->id, $asset->is_active ? 0 : 1]) }}'"
                                               {{ $asset->is_active ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.rentals.assets.edit', $asset->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.rentals.assets.destroy', $asset->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this asset?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted">{{ translate('No rental assets found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($assets->hasPages())
                <div class="card-footer">{{ $assets->links() }}</div>
            @endif
        </div>
    </div>
@endsection
