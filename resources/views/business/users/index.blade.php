@extends('business.layouts.app')

@section('title', translate('Users'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h1 class="page-header-title">{{ translate('Users') }}</h1>
        <a href="{{ route('business.users.create') }}" class="btn btn--primary">
            <i class="tio-add"></i> {{ translate('Add Team Member') }}
        </a>
    </div>

    @if($users->count() === 0)
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('No team members added yet') }}</h5>
            <p class="text-muted mb-3" style="color: #6c757d !important; max-width: 450px; margin: 0 auto;">
                {{ translate('Add team members to help manage routes, locations, and documents.') }}
            </p>
            <a href="{{ route('business.users.create') }}" class="btn btn--primary">
                {{ translate('Add Your First Team Member') }}
            </a>
        </div>
    </div>
    @else
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>{{ translate('Name') }}</th>
                            <th>{{ translate('Email') }}</th>
                            <th>{{ translate('Role') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Last Login') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $user->role ?? '-')) }}</td>
                            <td>
                                <span class="badge badge-soft-{{ $user->is_active ? 'success' : 'danger' }}">
                                    {{ $user->is_active ? translate('Active') : translate('Inactive') }}
                                </span>
                            </td>
                            <td>{{ $user->last_login_at?->diffForHumans() ?? translate('Never') }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('business.users.edit', $user->id) }}" class="btn btn-sm btn-outline-primary" title="{{ translate('Edit') }}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    @if($user->id !== auth('business')->id())
                                    <form action="{{ route('business.users.deactivate', $user->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ translate('Are you sure you want to toggle this user status?') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-{{ $user->is_active ? 'warning' : 'success' }}" title="{{ $user->is_active ? translate('Deactivate') : translate('Activate') }}">
                                            <i class="tio-{{ $user->is_active ? 'power_off' : 'power_on' }}"></i>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                {{ translate('No users found.') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
        <div class="card-footer">
            {{ $users->links() }}
        </div>
        @endif
    </div>
    @endif
@endsection
