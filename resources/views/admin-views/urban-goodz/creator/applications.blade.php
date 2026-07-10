@extends('layouts.admin.app')

@section('title', translate('Creator Applications'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <h1>{{ translate('Creator Applications') }}</h1>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search by name, email or username') }}" value="{{ request('search') }}"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                            <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>{{ translate('Suspended') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i> {{ translate('Filter') }}</button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead>
                        <tr><th>{{ translate('Name') }}</th><th>{{ translate('Email') }}</th><th>{{ translate('Platform') }}</th><th>{{ translate('Followers') }}</th><th>{{ translate('Niche') }}</th><th>{{ translate('City') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Actions') }}</th></tr>
                    </thead>
                    <tbody>
                    @forelse($applications as $app)
                        <tr>
                            <td>{{ $app->creator_name }}</td>
                            <td>{{ $app->email ?? 'N/A' }}</td>
                            <td>{{ $app->platform ?? 'N/A' }}</td>
                            <td>{{ number_format($app->follower_count ?? 0) }}</td>
                            <td>{{ $app->niche ?? 'N/A' }}</td>
                            <td>{{ $app->city ?? 'N/A' }}</td>
                            <td><span class="badge badge-{{ $app->status == 'approved' ? 'success' : ($app->status == 'rejected' || $app->status == 'suspended' ? 'danger' : 'warning') }}">{{ $app->status }}</span></td>
                            <td><a href="{{ route('admin.urban-goodz.creator.applications.show', $app->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">{{ translate('No applications found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($applications->hasPages())
                <div class="card-footer">{{ $applications->links() }}</div>
            @endif
        </div>
    </div>
@endsection
