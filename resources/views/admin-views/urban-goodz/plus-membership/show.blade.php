@extends('layouts.admin.app')

@section('title', translate('Membership Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ $membership->member_name }}</h1>
                    <p class="text-muted mb-0" style="color: #6c757d !important;">{{ $membership->member_email }}</p>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.plus-membership.edit', $membership->id) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('Edit') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.plus-membership.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            @php
                                $tierMap = ['basic' => 'secondary', 'premium' => 'primary', 'elite' => 'success'];
                            @endphp
                            <span class="badge badge-soft-{{ $tierMap[$membership->tier] ?? 'secondary' }}">
                                {{ ucfirst($membership->tier) }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Tier') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            @php
                                $statusMap = ['active' => 'success', 'expired' => 'danger', 'cancelled' => 'warning'];
                            @endphp
                            <span class="badge badge-soft-{{ $statusMap[$membership->status] ?? 'secondary' }}">
                                {{ ucfirst($membership->status) }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">${{ number_format($membership->monthly_fee, 2) }}</h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Monthly Fee') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $membership->expires_at?->format('M d, Y') ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Expires At') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>{{ translate('Subscribed At') }}:</strong> {{ $membership->subscribed_at?->format('M d, Y h:i A') ?? '-' }}</p>
                        <p><strong>{{ translate('Created') }}:</strong> {{ $membership->created_at?->format('M d, Y h:i A') ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Benefits') }}:</strong></p>
                        @if(is_array($membership->benefits) && count($membership->benefits))
                            <ul class="mb-0">
                                @foreach($membership->benefits as $benefit)
                                    <li>{{ $benefit }}</li>
                                @endforeach
                            </ul>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.urban-goodz.plus-membership.status', [$membership->id, 'active']) }}" class="btn btn-outline--success btn-sm">
                    {{ translate('Mark Active') }}
                </a>
                <a href="{{ route('admin.urban-goodz.plus-membership.status', [$membership->id, 'expired']) }}" class="btn btn-outline--danger btn-sm">
                    {{ translate('Mark Expired') }}
                </a>
                <a href="{{ route('admin.urban-goodz.plus-membership.status', [$membership->id, 'cancelled']) }}" class="btn btn-outline--warning btn-sm">
                    {{ translate('Cancel') }}
                </a>
            </div>
        </div>
    </div>
@endsection
