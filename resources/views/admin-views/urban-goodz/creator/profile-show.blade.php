@extends('layouts.admin.app')

@section('title', translate('Creator Profile'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ translate('Creator Profile') }}: {{ $profile->display_name ?? $profile->application->creator_name ?? 'N/A' }}</h1>
            <a href="{{ route('admin.urban-goodz.creator.profiles') }}" class="btn btn-secondary"><i class="tio-back"></i> {{ translate('Back') }}</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Profile Info') }}</h5></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th>{{ translate('Handle') }}</th><td>@ {{ $profile->handle }}</td></tr>
                            <tr><th>{{ translate('Display Name') }}</th><td>{{ $profile->display_name ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('City') }}</th><td>{{ $profile->city ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Zone') }}</th><td>{{ $profile->zone ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Approved') }}</th><td>{!! $profile->is_approved ? '<span class="badge badge-success">'.translate('Yes').'</span>' : '<span class="badge badge-warning">'.translate('No').'</span>' !!}</td></tr>
                            <tr><th>{{ translate('Featured') }}</th><td>{!! $profile->is_featured ? '<span class="badge badge-primary">'.translate('Yes').'</span>' : '<span class="badge badge-secondary">'.translate('No').'</span>' !!}</td></tr>
                            @if($profile->niches)<tr><th>{{ translate('Niches') }}</th><td>{{ implode(', ', $profile->niches) }}</td></tr>@endif
                        </table>
                        @if($profile->bio)<h6>{{ translate('Bio') }}</h6><p>{{ $profile->bio }}</p>@endif
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5>{{ translate('Performance Stats') }}</h5></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th>{{ translate('Total Content') }}</th><td>{{ $stats['total_content'] }}</td></tr>
                            <tr><th>{{ translate('Published') }}</th><td>{{ $stats['published_content'] }}</td></tr>
                            <tr><th>{{ translate('Total Earned') }}</th><td>{{\App\CentralLogics\Helpers::format_currency($stats['total_earned'])}}</td></tr>
                            <tr><th>{{ translate('Pending Earnings') }}</th><td>{{\App\CentralLogics\Helpers::format_currency($stats['pending_earnings'])}}</td></tr>
                            <tr><th>{{ translate('Total Leads') }}</th><td>{{ $stats['total_leads'] }}</td></tr>
                            <tr><th>{{ translate('Campaigns') }}</th><td>{{ $stats['total_campaigns'] }}</td></tr>
                            <tr><th>{{ translate('Engagement (Clicks)') }}</th><td>{{ number_format($stats['total_engagement']) }}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5>{{ translate('Update Profile') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.creator.profiles.update', $profile->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>{{ translate('Display Name') }}</label>
                                <input type="text" name="display_name" class="form-control" value="{{ $profile->display_name }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Handle') }}</label>
                                <input type="text" name="handle" class="form-control" value="{{ $profile->handle }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('City') }}</label>
                                <input type="text" name="city" class="form-control" value="{{ $profile->city }}">
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_approved" class="custom-control-input" id="pa_approved" value="1" {{ $profile->is_approved ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="pa_approved">{{ translate('Approved') }}</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_featured" class="custom-control-input" id="pa_featured" value="1" {{ $profile->is_featured ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="pa_featured">{{ translate('Featured') }}</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="2">{{ $profile->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">{{ translate('Update Profile') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Recent Content') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm m-0">
                            <thead><tr><th>{{ translate('Title') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Clicks') }}</th><th>{{ translate('Shoppable') }}</th><th>{{ translate('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse($profile->content as $item)
                                <tr>
                                    <td>{{ str_limit($item->title, 40) }}</td>
                                    <td>{{ $item->content_type }}</td>
                                    <td>{{ number_format($item->clicks_count) }}</td>
                                    <td>{!! $item->is_shoppable ? '<span class="badge badge-success">Yes</span>' : '<span class="badge badge-secondary">No</span>' !!}</td>
                                    <td><span class="badge badge-{{ $item->status == 'published' ? 'success' : 'secondary' }}">{{ $item->status }}</span></td>
                                    <td><a href="{{ route('admin.urban-goodz.creator.content.show', $item->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center">{{ translate('No content') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header"><h5>{{ translate('Campaigns') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm m-0">
                            <thead><tr><th>{{ translate('Campaign') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Approval') }}</th></tr></thead>
                            <tbody>
                            @forelse($profile->campaigns as $ca)
                                <tr>
                                    <td>{{ $ca->campaign->title ?? 'N/A' }}</td>
                                    <td><span class="badge badge-soft-info">{{ $ca->campaign->status ?? 'N/A' }}</span></td>
                                    <td><span class="badge badge-{{ $ca->approval_status == 'approved' ? 'success' : 'warning' }}">{{ $ca->approval_status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center">{{ translate('No campaigns') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($profile->earnings->count())
                <div class="card mt-3">
                    <div class="card-header"><h5>{{ translate('Recent Earnings') }}</h5></div>
                    <div class="table-responsive">
                        <table class="table table-sm m-0">
                            <thead><tr><th>{{ translate('Type') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th></tr></thead>
                            <tbody>
                            @foreach($profile->earnings as $e)
                                <tr>
                                    <td>{{ $e->type }}</td>
                                    <td>{{\App\CentralLogics\Helpers::format_currency($e->amount)}}</td>
                                    <td><span class="badge badge-{{ $e->status == 'paid' ? 'success' : ($e->status == 'pending' ? 'warning' : 'info') }}">{{ $e->status }}</span></td>
                                    <td>{{ $e->created_at->format('M d, Y') }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
