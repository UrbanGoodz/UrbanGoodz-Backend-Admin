@extends('layouts.admin.app')

@section('title', translate('Business Clients'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title">{{ translate('Business Clients') }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.business-clients.create') }}" class="btn btn--primary">
                    <i class="tio-add"></i> {{ translate('Create') }}
                </a>
                <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back to Control Center') }}</a>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Company') }}</th>
                            <th>{{ translate('Email') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Users') }}</th>
                            <th>{{ translate('Jobs') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Date') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($clients as $key => $c)
                            <tr>
                                <td>{{ $clients->firstItem() + $key }}</td>
                                <td class="font-weight-bold">
                                    <a href="{{ route('admin.urban-goodz.business-clients.show', $c->id) }}">{{ $c->company_name }}</a>
                                </td>
                                <td>{{ $c->email }}</td>
                                <td>{{ $c->business_type ?? translate('N/A') }}</td>
                                <td>{{ $c->users_count }}</td>
                                <td>{{ $c->jobs_count }}</td>
                                <td>
                                    @php
                                        $statusBadge = ['pending' => 'badge-soft-warning', 'approved' => 'badge-soft-success', 'suspended' => 'badge-soft-danger', 'inactive' => 'badge-soft-secondary'];
                                    @endphp
                                    <span class="badge {{ $statusBadge[$c->status] ?? 'badge-soft-info' }}">{{ $c->status }}</span>
                                </td>
                                <td>{{ $c->created_at->format('M d, Y') }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline--primary dropdown-toggle" type="button" data-toggle="dropdown">
                                            {{ translate('Actions') }}
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-clients.show', $c->id) }}">
                                                {{ translate('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-clients.edit', $c->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            @if($c->status === 'pending')
                                                <form method="POST" action="{{ route('admin.urban-goodz.business-clients.approve', $c->id) }}" class="d-inline">
                                                    @csrf
                                                    <button class="dropdown-item" type="submit">{{ translate('Approve') }}</button>
                                                </form>
                                            @endif
                                            @if($c->status === 'approved')
                                                <form method="POST" action="{{ route('admin.urban-goodz.business-clients.suspend', $c->id) }}" class="d-inline">
                                                    @csrf
                                                    <button class="dropdown-item text-danger" type="submit">{{ translate('Suspend') }}</button>
                                                </form>
                                            @endif
                                            @if($c->status === 'suspended')
                                                <form method="POST" action="{{ route('admin.urban-goodz.business-clients.reactivate', $c->id) }}" class="d-inline">
                                                    @csrf
                                                    <button class="dropdown-item" type="submit">{{ translate('Reactivate') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($clients) === 0)
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No business clients found') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $clients->links() }}</div>
        </div>
    </div>
@endsection
