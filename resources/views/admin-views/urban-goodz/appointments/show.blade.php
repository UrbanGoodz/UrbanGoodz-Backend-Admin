@extends('layouts.admin.app')

@section('title', translate('Appointment Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ translate('Appointment') }} #{{ $appointment->id }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.appointments.edit', $appointment->id) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('Edit') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.appointments.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0 text--primary">
                            @php
                                $statusMap = ['pending' => 'warning', 'confirmed' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
                            @endphp
                            <span class="badge badge-soft-{{ $statusMap[$appointment->status] ?? 'secondary' }}">
                                {{ ucfirst($appointment->status) }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $appointment->scheduled_at?->format('M d, Y h:i A') ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Scheduled At') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $appointment->completed_at?->format('M d, Y h:i A') ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Completed At') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $appointment->created_at?->format('M d, Y') ?? '-' }}</h6>
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
                        <p><strong>{{ translate('Service Request') }}:</strong>
                            @if($appointment->service_request_id)
                                <a href="{{ route('admin.urban-goodz.service-requests.show', $appointment->service_request_id) }}">
                                    #{{ $appointment->service_request_id }}
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                        <p><strong>{{ translate('Service Provider') }}:</strong>
                            @if($appointment->service_provider_id)
                                <a href="{{ route('admin.urban-goodz.service-providers.show', $appointment->service_provider_id) }}">
                                    #{{ $appointment->service_provider_id }}
                                </a>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Notes') }}:</strong></p>
                        <p>{{ $appointment->notes ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Quick Actions') }}</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('admin.urban-goodz.appointments.status', [$appointment->id, 'confirmed']) }}" class="btn btn-outline--info btn-sm">
                    {{ translate('Mark Confirmed') }}
                </a>
                <a href="{{ route('admin.urban-goodz.appointments.status', [$appointment->id, 'in_progress']) }}" class="btn btn-outline--primary btn-sm">
                    {{ translate('Mark In Progress') }}
                </a>
                <a href="{{ route('admin.urban-goodz.appointments.status', [$appointment->id, 'completed']) }}" class="btn btn-outline--success btn-sm">
                    {{ translate('Mark Completed') }}
                </a>
                <a href="{{ route('admin.urban-goodz.appointments.status', [$appointment->id, 'cancelled']) }}" class="btn btn-outline--danger btn-sm">
                    {{ translate('Cancel') }}
                </a>
            </div>
        </div>
    </div>
@endsection
