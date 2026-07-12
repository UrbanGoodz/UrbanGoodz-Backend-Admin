@extends('layouts.admin.app')

@section('title', translate('Marketplace Item Details'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Marketplace Item') }}</h1>
            <a href="{{ route('admin.urban-goodz.community.marketplace') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-arrow-left"></i> {{ translate('Back to Marketplace') }}
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3>{{ $item->title }}</h3>
                                <small class="text-muted">
                                    {{ translate('By') }} {{ $item->seller_name ?? 'Unknown' }}
                                    &middot; {{ $item->created_at->format('M d, Y') }}
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                @if($item->condition)
                                <span class="badge badge-soft-info">{{ ucfirst($item->condition) }}</span>
                                @endif
                                @php $statusColors = ['available' => 'success', 'sold' => 'secondary', 'reserved' => 'warning', 'expired' => 'danger']; @endphp
                                <span class="badge badge-soft-{{ $statusColors[$item->status] ?? 'secondary' }}">{{ ucfirst($item->status) }}</span>
                                @if($item->is_active)
                                    <span class="badge badge-soft-success">{{ translate('Active') }}</span>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('Inactive') }}</span>
                                @endif
                            </div>
                        </div>
                        <hr>
                        <div class="mt-3">{{ nl2br(e($item->description)) }}</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Item Details') }}</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">{{ translate('ID') }}</dt>
                            <dd class="col-sm-7">{{ $item->id }}</dd>

                            <dt class="col-sm-5">{{ translate('Price') }}</dt>
                            <dd class="col-sm-7"><strong>{{ $item->currency ?? '$' }}{{ number_format($item->price, 2) }}</strong></dd>

                            <dt class="col-sm-5">{{ translate('Condition') }}</dt>
                            <dd class="col-sm-7">{{ ucfirst($item->condition) ?? '-' }}</dd>

                            <dt class="col-sm-5">{{ translate('Status') }}</dt>
                            <dd class="col-sm-7">{{ ucfirst($item->status) }}</dd>

                            <dt class="col-sm-5">{{ translate('Seller') }}</dt>
                            <dd class="col-sm-7">{{ $item->seller_name ?? '-' }}</dd>

                            <dt class="col-sm-5">{{ translate('Contact') }}</dt>
                            <dd class="col-sm-7">{{ $item->seller_contact ?? '-' }}</dd>

                            <dt class="col-sm-5">{{ translate('Location') }}</dt>
                            <dd class="col-sm-7">{{ $item->location ?? '-' }}</dd>

                            <dt class="col-sm-5">{{ translate('Active') }}</dt>
                            <dd class="col-sm-7">
                                @if($item->is_active)
                                    <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                @endif
                            </dd>

                            <dt class="col-sm-5">{{ translate('Created') }}</dt>
                            <dd class="col-sm-7">{{ $item->created_at->format('M d, Y') }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Actions') }}</h5>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <form method="POST" action="{{ route('admin.urban-goodz.community.marketplace.toggle-active', $item->id) }}">
                            @csrf
                            @if($item->is_active)
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="tio-archive"></i> {{ translate('Deactivate') }}
                            </button>
                            @else
                            <button type="submit" class="btn btn-success w-100">
                                <i class="tio-check-circle"></i> {{ translate('Activate') }}
                            </button>
                            @endif
                        </form>

                        <form method="POST" action="{{ route('admin.urban-goodz.community.marketplace.status', $item->id) }}">
                            @csrf
                            <label class="form-label">{{ translate('Change Status') }}</label>
                            <div class="input-group">
                                <select name="status" class="form-select form-select-sm">
                                    @foreach(['available', 'sold', 'reserved', 'expired'] as $status)
                                    <option value="{{ $status }}" {{ $item->status === $status ? 'selected' : '' }}>{{ ucfirst($status) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn--primary">{{ translate('Update') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
