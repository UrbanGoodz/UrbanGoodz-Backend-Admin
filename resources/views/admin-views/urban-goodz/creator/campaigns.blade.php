@extends('layouts.admin.app')

@section('title', translate('Creator Campaigns'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ translate('Creator Campaigns') }}</h1>
            <a href="{{ route('admin.urban-goodz.creator.campaigns.create') }}" class="btn btn-primary"><i class="tio-add"></i> {{ translate('New Campaign') }}</a>
        </div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search campaigns') }}" value="{{ request('search') }}"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                            <option value="open" {{ request('status') == 'open' ? 'selected' : '' }}>{{ translate('Open') }}</option>
                            <option value="assigned" {{ request('status') == 'assigned' ? 'selected' : '' }}>{{ translate('Assigned') }}</option>
                            <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>{{ translate('In Progress') }}</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ translate('Completed') }}</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="product_review" {{ request('type') == 'product_review' ? 'selected' : '' }}>{{ translate('Product Review') }}</option>
                            <option value="vendor_spotlight" {{ request('type') == 'vendor_spotlight' ? 'selected' : '' }}>{{ translate('Vendor Spotlight') }}</option>
                            <option value="food_review" {{ request('type') == 'food_review' ? 'selected' : '' }}>{{ translate('Food Review') }}</option>
                            <option value="event_promo" {{ request('type') == 'event_promo' ? 'selected' : '' }}>{{ translate('Event Promo') }}</option>
                            <option value="rental_showcase" {{ request('type') == 'rental_showcase' ? 'selected' : '' }}>{{ translate('Rental Showcase') }}</option>
                            <option value="fashion_styling" {{ request('type') == 'fashion_styling' ? 'selected' : '' }}>{{ translate('Fashion Styling') }}</option>
                            <option value="service_provider" {{ request('type') == 'service_provider' ? 'selected' : '' }}>{{ translate('Service Provider') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Title') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Pay') }}</th><th>{{ translate('Assignments') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Deadline') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($campaigns as $c)
                        <tr>
                            <td>{{ $c->title }}</td>
                            <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', $c->type) }}</span></td>
                            <td>{{ $c->pay_type }} @if($c->flat_payout) ({{\App\CentralLogics\Helpers::format_currency($c->flat_payout)}}) @endif</td>
                            <td>{{ $c->assignments_count ?? 0 }}</td>
                            <td><span class="badge badge-{{ $c->status == 'completed' ? 'success' : ($c->status == 'cancelled' ? 'danger' : ($c->status == 'in_progress' ? 'info' : ($c->status == 'assigned' ? 'primary' : 'secondary'))) }}">{{ str_replace('_', ' ', $c->status) }}</span></td>
                            <td>{{ $c->deadline ? $c->deadline->format('M d, Y') : 'N/A' }}</td>
                            <td><a href="{{ route('admin.urban-goodz.creator.campaigns.show', $c->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">{{ translate('No campaigns found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($campaigns->hasPages())
                <div class="card-footer">{{ $campaigns->links() }}</div>
            @endif
        </div>
    </div>
@endsection
