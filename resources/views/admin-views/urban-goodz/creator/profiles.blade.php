@extends('layouts.admin.app')

@section('title', translate('Creator Profiles'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ translate('Creator Profiles') }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.creator.dashboard') }}" class="btn btn-secondary"><i class="tio-back"></i> {{ translate('Dashboard') }}</a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search by name, handle or city') }}" value="{{ request('search') }}"></div>
                    <div class="col-md-2">
                        <select name="is_approved" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Approval') }}</option>
                            <option value="1" {{ request('is_approved') == '1' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                            <option value="0" {{ request('is_approved') == '0' ? 'selected' : '' }}>{{ translate('Unapproved') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="is_featured" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Featured') }}</option>
                            <option value="1" {{ request('is_featured') == '1' ? 'selected' : '' }}>{{ translate('Featured') }}</option>
                            <option value="0" {{ request('is_featured') == '0' ? 'selected' : '' }}>{{ translate('Not Featured') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Handle') }}</th><th>{{ translate('City') }}</th><th>{{ translate('Niches') }}</th><th>{{ translate('Approved') }}</th><th>{{ translate('Featured') }}</th><th>{{ translate('Actions') }}</th></tr></thead>
                    <tbody>
                    @forelse($profiles as $profile)
                        <tr>
                            <td>{{ $profile->display_name ?? $profile->application->creator_name ?? 'N/A' }}</td>
                            <td>@ {{ $profile->handle ?? 'N/A' }}</td>
                            <td>{{ $profile->city ?? 'N/A' }}</td>
                            <td>{{ $profile->niches ? implode(', ', $profile->niches) : 'N/A' }}</td>
                            <td>{!! $profile->is_approved ? '<span class="badge badge-success">'.translate('Yes').'</span>' : '<span class="badge badge-warning">'.translate('No').'</span>' !!}</td>
                            <td>{!! $profile->is_featured ? '<span class="badge badge-primary">'.translate('Yes').'</span>' : '<span class="badge badge-secondary">'.translate('No').'</span>' !!}</td>
                            <td><a href="{{ route('admin.urban-goodz.creator.profiles.show', $profile->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">{{ translate('No profiles found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($profiles->hasPages())
                <div class="card-footer">{{ $profiles->links() }}</div>
            @endif
        </div>
    </div>
@endsection
