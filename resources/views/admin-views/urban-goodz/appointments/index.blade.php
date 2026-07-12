@extends('layouts.admin.app')

@section('title', translate('Appointments'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Appointments') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $appointments->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.appointments.create') }}" class="btn btn--primary">
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
                                   placeholder="{{ translate('Search notes or status') }}"
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
                            <th>{{ translate('Service Request') }}</th>
                            <th>{{ translate('Provider') }}</th>
                            <th>{{ translate('Scheduled At') }}</th>
                            <th>{{ translate('Completed At') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($appointments as $key => $appointment)
                            <tr>
                                <td>{{ $appointments->firstItem() + $key }}</td>
                                <td>
                                    @if($appointment->service_request_id)
                                        <a href="{{ route('admin.urban-goodz.service-requests.show', $appointment->service_request_id) }}" class="text--primary">
                                            #{{ $appointment->service_request_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($appointment->service_provider_id)
                                        <a href="{{ route('admin.urban-goodz.service-providers.show', $appointment->service_provider_id) }}" class="text--primary">
                                            #{{ $appointment->service_provider_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $appointment->scheduled_at?->format('M d, Y h:i A') ?? '-' }}</td>
                                <td>{{ $appointment->completed_at?->format('M d, Y h:i A') ?? '-' }}</td>
                                <td>
                                    @php
                                        $statusMap = ['pending' => 'warning', 'confirmed' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
                                        $badge = $statusMap[$appointment->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge badge-soft-{{ $badge }}">{{ ucfirst($appointment->status) }}</span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.appointments.show', $appointment->id) }}">
                                                <i class="tio-eye"></i> {{ translate('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.appointments.edit', $appointment->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.appointments.destroy', $appointment->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this appointment?') }}')">
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

            @if($appointments->hasPages())
                <div class="card-footer">
                    {{ $appointments->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
