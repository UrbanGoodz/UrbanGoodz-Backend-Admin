@extends('business.layouts.dispatcher')
@section('title', translate('Team Members'))
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h1 class="page-header-title">{{ translate('Team Members') }}</h1>
    @if(auth('business')->user()->hasDispatchPermission('dispatch_users_manage'))
    <a href="{{ route('dispatcher.users.create') }}" class="btn btn--primary"><i class="tio-add"></i> {{ translate('Add Member') }}</a>
    @endif
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Name') }}</th>
                        <th>{{ translate('Email') }}</th>
                        <th>{{ translate('Role') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th>{{ translate('Last Login') }}</th>
                        @if(auth('business')->user()->hasDispatchPermission('dispatch_users_manage'))
                        <th>{{ translate('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                    <tr>
                        <td class="fw-bold">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td>
                            @php($roleLabels = ['dispatch_owner' => 'Owner', 'dispatch_manager' => 'Manager', 'dispatcher' => 'Dispatcher', 'dispatch_readonly' => 'Read-Only', 'dispatch_finance' => 'Finance'])
                            <span class="badge badge-soft-primary">{{ $roleLabels[$u->role] ?? ucfirst(str_replace('_',' ',$u->role)) }}</span>
                        </td>
                        <td class="text-center">
                            @if($u->is_active && $u->status === 'active')
                            <span class="badge badge-soft-success">{{ translate('Active') }}</span>
                            @else
                            <span class="badge badge-soft-danger">{{ translate('Inactive') }}</span>
                            @endif
                        </td>
                        <td>{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : translate('Never') }}</td>
                        @if(auth('business')->user()->hasDispatchPermission('dispatch_users_manage'))
                        <td>
                            @if($u->role !== 'dispatch_owner')
                            <a href="{{ route('dispatcher.users.edit', $u->id) }}" class="btn btn-sm btn-outline--primary"><i class="tio-edit"></i></a>
                            @if($u->id !== auth('business')->id())
                            <form method="POST" action="{{ route('dispatcher.users.deactivate', $u->id) }}" class="d-inline" onsubmit="return confirm('{{ translate('Deactivate this user?') }}')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline--danger"><i class="tio-delete"></i></button>
                            </form>
                            @endif
                            @endif
                        </td>
                        @endif
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ translate('No team members') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
