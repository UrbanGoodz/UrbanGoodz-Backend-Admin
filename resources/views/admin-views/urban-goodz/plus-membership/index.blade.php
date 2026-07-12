@extends('layouts.admin.app')

@section('title', translate('Plus Memberships'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('Plus Memberships') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $memberships->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.plus-membership.create') }}" class="btn btn--primary">
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
                                   placeholder="{{ translate('Search by name or email') }}"
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
                            <th>{{ translate('Member Name') }}</th>
                            <th>{{ translate('Email') }}</th>
                            <th>{{ translate('Tier') }}</th>
                            <th>{{ translate('Monthly Fee') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Expires At') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($memberships as $key => $membership)
                            <tr>
                                <td>{{ $memberships->firstItem() + $key }}</td>
                                <td>{{ $membership->member_name }}</td>
                                <td>{{ $membership->member_email }}</td>
                                <td>
                                    @php
                                        $tierMap = ['basic' => 'secondary', 'premium' => 'primary', 'elite' => 'success'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $tierMap[$membership->tier] ?? 'secondary' }}">
                                        {{ ucfirst($membership->tier) }}
                                    </span>
                                </td>
                                <td>${{ number_format($membership->monthly_fee, 2) }}</td>
                                <td>
                                    @php
                                        $statusMap = ['active' => 'success', 'expired' => 'danger', 'cancelled' => 'warning'];
                                    @endphp
                                    <span class="badge badge-soft-{{ $statusMap[$membership->status] ?? 'secondary' }}">
                                        {{ ucfirst($membership->status) }}
                                    </span>
                                </td>
                                <td>{{ $membership->expires_at?->format('M d, Y') ?? '-' }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.plus-membership.show', $membership->id) }}">
                                                <i class="tio-eye"></i> {{ translate('View') }}
                                            </a>
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.plus-membership.edit', $membership->id) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.plus-membership.destroy', $membership->id) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this membership?') }}')">
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

            @if($memberships->hasPages())
                <div class="card-footer">
                    {{ $memberships->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
