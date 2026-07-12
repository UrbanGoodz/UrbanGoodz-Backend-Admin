@extends('layouts.admin.app')

@section('title', translate('Service Request Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Service Request') }} #{{ $serviceRequest->id }}</h1>
                    <p class="text-muted mb-0" style="color: #6c757d !important;">{{ $serviceRequest->customer_name }} &middot; {{ $serviceRequest->customer_email }}</p>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.service-requests.edit', $serviceRequest->id) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('Edit') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.service-requests.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            @php
                                $statusMap = ['pending' => 'warning', 'assigned' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
                            @endphp
                            <span class="badge badge-soft-{{ $statusMap[$serviceRequest->status] ?? 'secondary' }}">
                                {{ ucfirst(str_replace('_', ' ', $serviceRequest->status)) }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $serviceRequest->service_type }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Service Type') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $serviceRequest->location ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Location') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $serviceRequest->created_at?->format('M d, Y') ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Created') }}</small>
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
                        <p><strong>{{ translate('Customer Name') }}:</strong> {{ $serviceRequest->customer_name }}</p>
                        <p><strong>{{ translate('Email') }}:</strong> {{ $serviceRequest->customer_email ?? '-' }}</p>
                        <p><strong>{{ translate('Phone') }}:</strong> {{ $serviceRequest->customer_phone ?? '-' }}</p>
                        <p><strong>{{ translate('Assigned Vendor ID') }}:</strong> {{ $serviceRequest->assigned_vendor_id ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Description') }}:</strong></p>
                        <p>{{ $serviceRequest->description ?? '-' }}</p>
                        <p><strong>{{ translate('Admin Notes') }}:</strong></p>
                        <p>{{ $serviceRequest->admin_notes ?? '-' }}</p>
                    </div>
                </div>
                @if(is_array($serviceRequest->preferred_dates) && count($serviceRequest->preferred_dates))
                    <hr>
                    <p><strong>{{ translate('Preferred Dates') }}:</strong></p>
                    <div>
                        @foreach($serviceRequest->preferred_dates as $date)
                            <span class="badge badge-soft-info">{{ $date }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.urban-goodz.service-requests.status', [$serviceRequest->id, 'pending']) }}" class="btn btn-outline--warning btn-sm">
                    {{ translate('Mark Pending') }}
                </a>
                <a href="{{ route('admin.urban-goodz.service-requests.status', [$serviceRequest->id, 'assigned']) }}" class="btn btn-outline--info btn-sm">
                    {{ translate('Mark Assigned') }}
                </a>
                <a href="{{ route('admin.urban-goodz.service-requests.status', [$serviceRequest->id, 'in_progress']) }}" class="btn btn-outline--primary btn-sm">
                    {{ translate('Mark In Progress') }}
                </a>
                <a href="{{ route('admin.urban-goodz.service-requests.status', [$serviceRequest->id, 'completed']) }}" class="btn btn-outline--success btn-sm">
                    {{ translate('Mark Completed') }}
                </a>
                <a href="{{ route('admin.urban-goodz.service-requests.status', [$serviceRequest->id, 'cancelled']) }}" class="btn btn-outline--danger btn-sm">
                    {{ translate('Cancel') }}
                </a>
            </div>
        </div>
    </div>
@endsection
