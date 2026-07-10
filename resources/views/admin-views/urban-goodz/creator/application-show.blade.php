@extends('layouts.admin.app')

@section('title', translate('Application Detail'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ translate('Application Detail') }}: {{ $application->creator_name }}</h1>
            <a href="{{ route('admin.urban-goodz.creator.applications') }}" class="btn btn-secondary"><i class="tio-back"></i> {{ translate('Back') }}</a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Application Info') }}</h5></div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr><th>{{ translate('Name') }}</th><td>{{ $application->creator_name }}</td></tr>
                            <tr><th>{{ translate('Email') }}</th><td>{{ $application->email ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Phone') }}</th><td>{{ $application->phone ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Platform') }}</th><td>{{ $application->platform ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Username') }}</th><td>{{ $application->username ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Followers') }}</th><td>{{ number_format($application->follower_count ?? 0) }}</td></tr>
                            <tr><th>{{ translate('Niche') }}</th><td>{{ $application->niche ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('City') }}</th><td>{{ $application->city ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Market') }}</th><td>{{ $application->market ?? 'N/A' }}</td></tr>
                            <tr><th>{{ translate('Status') }}</th><td><span class="badge badge-{{ $application->status == 'approved' ? 'success' : ($application->status == 'rejected' || $application->status == 'suspended' ? 'danger' : 'warning') }}">{{ $application->status }}</span></td></tr>
                            <tr><th>{{ translate('Submitted') }}</th><td>{{ $application->created_at->format('M d, Y g:i A') }}</td></tr>
                        </table>

                        @if($application->bio)
                            <h6 class="mt-3">{{ translate('Bio') }}</h6>
                            <p>{{ $application->bio }}</p>
                        @endif

                        @if($application->social_links)
                            <h6 class="mt-3">{{ translate('Social Links') }}</h6>
                            <pre class="bg-light p-2 rounded">@json($application->social_links, JSON_PRETTY_PRINT)</pre>
                        @endif

                        @if($application->content_samples)
                            <h6 class="mt-3">{{ translate('Content Samples') }}</h6>
                            <pre class="bg-light p-2 rounded">@json($application->content_samples, JSON_PRETTY_PRINT)</pre>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Update Status') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.creator.applications.status', $application->id) }}">
                            @csrf
                            <div class="form-group">
                                <label>{{ translate('Status') }}</label>
                                <select name="status" class="form-control" required>
                                    <option value="pending" {{ $application->status == 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                                    <option value="approved" {{ $application->status == 'approved' ? 'selected' : '' }}>{{ translate('Approve') }}</option>
                                    <option value="rejected" {{ $application->status == 'rejected' ? 'selected' : '' }}>{{ translate('Reject') }}</option>
                                    <option value="suspended" {{ $application->status == 'suspended' ? 'selected' : '' }}>{{ translate('Suspend') }}</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Admin Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="3">{{ $application->admin_notes }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">{{ translate('Update') }}</button>
                        </form>
                    </div>
                </div>

                @if($application->profile)
                    <div class="card mt-3">
                        <div class="card-header"><h5>{{ translate('Creator Profile') }}</h5></div>
                        <div class="card-body">
                            <p><strong>{{ translate('Handle') }}:</strong> @ {{ $application->profile->handle }}</p>
                            <p><strong>{{ translate('Featured') }}:</strong> {{ $application->profile->is_featured ? translate('Yes') : translate('No') }}</p>
                            <a href="{{ route('admin.urban-goodz.creator.profiles.show', $application->profile->id) }}" class="btn btn-sm btn-info">{{ translate('View Profile') }}</a>
                        </div>
                    </div>
                @endif

                @if($application->products->count())
                    <div class="card mt-3">
                        <div class="card-header"><h5>{{ translate('Products') }} ({{ $application->products->count() }})</h5></div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach($application->products as $product)
                                    <li class="list-group-item">{{ $product->name }} <span class="float-right">${{ number_format($product->price ?? 0, 2) }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
