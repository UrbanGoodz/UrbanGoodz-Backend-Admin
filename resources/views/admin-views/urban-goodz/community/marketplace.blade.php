@extends('layouts.admin.app')

@section('title', translate('Community Marketplace'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Community Marketplace') }}</h1>
            <a href="{{ route('admin.urban-goodz.community.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-arrow-left"></i> {{ translate('Dashboard') }}
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">{{ translate('Search') }}</label>
                        <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="{{ translate('Title, seller, location...') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Status') }}</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            @foreach(['available', 'sold', 'reserved', 'expired'] as $status)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Active') }}</label>
                        <select name="active" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            <option value="1" {{ request('active') === '1' ? 'selected' : '' }}>{{ translate('Yes') }}</option>
                            <option value="0" {{ request('active') === '0' ? 'selected' : '' }}>{{ translate('No') }}</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn--primary flex-grow-1"><i class="tio-search"></i> {{ translate('Search') }}</button>
                        @if(request('q') || request('status') || request('active'))
                        <a href="{{ route('admin.urban-goodz.community.marketplace') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Seller') }}</th>
                                <th>{{ translate('Price') }}</th>
                                <th>{{ translate('Condition') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Active') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($items as $item)
                            <tr>
                                <td>{{ $item->id }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.community.marketplace.show', $item->id) }}">
                                        <strong>{{ Str::limit($item->title, 40) }}</strong>
                                    </a>
                                </td>
                                <td>{{ $item->seller_name ?? '-' }}</td>
                                <td><strong>{{ $item->currency ?? '$' }}{{ number_format($item->price, 2) }}</strong></td>
                                <td>
                                    @if($item->condition)
                                    <span class="badge badge-soft-info">{{ ucfirst($item->condition) }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php $statusColors = ['available' => 'success', 'sold' => 'secondary', 'reserved' => 'warning', 'expired' => 'danger']; @endphp
                                    <span class="badge badge-soft-{{ $statusColors[$item->status] ?? 'secondary' }}">{{ ucfirst($item->status) }}</span>
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.urban-goodz.community.marketplace.toggle-active', $item->id) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $item->is_active ? 'success' : 'secondary' }}">
                                            {{ $item->is_active ? translate('Yes') : translate('No') }}
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.urban-goodz.community.marketplace.show', $item->id) }}" class="btn btn-sm btn-outline--primary" title="{{ translate('View') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.urban-goodz.community.marketplace.status', $item->id) }}" class="d-inline">
                                            @csrf
                                            <select name="status" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                                @foreach(['available', 'sold', 'reserved', 'expired'] as $status)
                                                <option value="{{ $status }}" {{ $item->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">{{ translate('No marketplace items found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $items->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
