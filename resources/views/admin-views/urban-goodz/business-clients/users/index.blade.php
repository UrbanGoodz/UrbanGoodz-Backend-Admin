@extends('layouts.admin.app')

@section('title', $client->company_name . ' - ' . translate('Users'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
            <h1 class="page-header-title">{{ $client->company_name }} {{ translate('Users') }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.business-clients.users.create', $client->id) }}" class="btn btn--primary">
                    <i class="tio-add"></i> {{ translate('Add User') }}
                </a>
                <a href="{{ route('admin.urban-goodz.business-clients.show', $client->id) }}" class="btn btn--secondary">
                    <i class="tio-back"></i> {{ translate('Back to Client') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Email') }}</th>
                            <th>{{ translate('Phone') }}</th>
                            <th>{{ translate('Role') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Last Login') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $key => $u)
                            <tr>
                                <td>{{ $users->firstItem() + $key }}</td>
                                <td class="font-weight-bold">{{ $u->first_name }} {{ $u->last_name }}</td>
                                <td>{{ $u->email }}</td>
                                <td>{{ $u->phone ?? translate('N/A') }}</td>
                                <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', $u->role) }}</span></td>
                                <td>
                                    @php $userStatusColor = ['active'=>'success', 'inactive'=>'secondary', 'suspended'=>'danger']; @endphp
                                    <span class="badge badge-soft-{{ $userStatusColor[$u->status ?? ($u->is_active ? 'active' : 'inactive')] ?? 'info' }}">{{ $u->status ?? ($u->is_active ? 'active' : 'inactive') }}</span>
                                </td>
                                <td>{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : translate('Never') }}</td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-toggle="dropdown">
                                            <i class="tio-settings"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <a class="dropdown-item" href="{{ route('admin.urban-goodz.business-clients.users.edit', [$client->id, $u->id]) }}">
                                                <i class="tio-edit"></i> {{ translate('Edit') }}
                                            </a>
                                            <form action="{{ route('admin.urban-goodz.business-clients.users.destroy', [$client->id, $u->id]) }}" method="POST" onsubmit="return confirm('{{ translate('Delete this user?') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="tio-delete"></i> {{ translate('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($users) === 0)
                            <tr><td colspan="8" class="text-center text-muted">{{ translate('No users found') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $users->links() }}</div>
        </div>
    </div>
@endsection
