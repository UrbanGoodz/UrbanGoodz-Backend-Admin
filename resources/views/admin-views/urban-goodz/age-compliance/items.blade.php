@extends('layouts.admin.app')

@section('title', translate('Age-Restricted Items'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Age-Restricted Items') }}</h1>
            <a href="{{ route('admin.urban-goodz.age-compliance.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Compliance') }}
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ translate('Items / Products') }}</h5>
                <form method="GET">
                    <select name="age_restricted_type" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Types') }}</option>
                        @foreach(['alcohol', 'thc_cbd', 'tobacco', 'adult', 'other'] as $t)
                        <option value="{{ $t }}" {{ request('age_restricted_type') === $t ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $t)) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Name') }}</th>
                                <th>{{ translate('Store') }}</th>
                                <th>{{ translate('Category') }}</th>
                                <th>{{ translate('Price') }}</th>
                                <th>{{ translate('Restricted Type') }}</th>
                                <th>{{ translate('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td>{{ Str::limit($item->name ?? '', 40) }}</td>
                                <td>{{ $item->store?->name ?? '-' }}</td>
                                <td>{{ $item->category?->name ?? '-' }}</td>
                                <td>\${{ number_format($item->price ?? 0, 2) }}</td>
                                <td>
                                    @if($item->age_restricted_type)
                                    <span class="badge badge-soft-warning">{{ ucfirst(str_replace('_', ' ', $item->age_restricted_type)) }}</span>
                                    @else
                                    <span class="badge badge-soft-danger">{{ translate('Restricted') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-soft-{{ $item->status ? 'success' : 'danger' }}">
                                        {{ $item->status ? translate('Active') : translate('Inactive') }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No age-restricted items found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $items->links() }}
            </div>
        </div>
    </div>
@endsection
