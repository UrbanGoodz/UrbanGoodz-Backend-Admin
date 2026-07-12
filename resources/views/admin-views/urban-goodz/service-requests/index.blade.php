@extends('layouts.admin.app')

@section('title', translate('Service Requests'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Service Requests') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $requests->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.service-requests.create') }}" class="btn btn--primary">
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
                                   placeholder="{{ translate('Search by name, email, or service type') }}"
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
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Service Type') }}</th>
                            <th>{{ translate('Location') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($requests as $key => $sr)
                            <tr>
                                <td>{{ $requests->firstItem() + $key }}</td>
                                <td>
                                    <div>{{ $sr->customer_name }}</div>
                                    <small class="text-muted" style="color: #6c757d !important;">{{ $sr->customer_email ?? '-' }}</small>
                                </td>
                                <td>{{ $sr->service_type }}</td>
                                <td>{{ $sr->location ?? '-' }}</td>
                                <td>
                                    @php
                                        $statusMap = ['pending' => 'warning', 'assigned' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'cancelled' => 'danger'];
                                        $badge = $statusMap[$sr->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge badge-soft-{{ $badge }}">{{ ucfirst(str_replace('_', ' ', $sr->status)) }}</span>
                                </td>
                                <td>{{ $sr->created_at?->format('M d, Y') ?? '-' }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.service-requests.show', $sr->id) }}">
                                                <i class="tio-eye"></i> {{ translate('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.service-requests.edit', $sr->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.service-requests.destroy', $sr->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this request?') }}')">
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

            @if($requests->hasPages())
                <div class="card-footer">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
