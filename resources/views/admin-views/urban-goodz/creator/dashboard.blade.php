@extends('layouts.admin.app')

@section('title', translate('Creator Commerce Dashboard'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Creator Commerce Dashboard') }}</h1>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Pending Applications') }}</h6>
                        <h3 class="text-warning">{{ $data['pending_applications'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Active Creators') }}</h6>
                        <h3 class="text-info">{{ $data['active_creators'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Active Campaigns') }}</h6>
                        <h3 class="text-primary">{{ $data['active_campaigns'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Total Revenue') }}</h6>
                        <h3 class="text-success">{{\App\CentralLogics\Helpers::format_currency($data['total_revenue'])}}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Pending Payouts') }}</h6>
                        <h3 class="text-danger">{{\App\CentralLogics\Helpers::format_currency($data['total_earnings_pending'])}}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Business Leads') }}</h6>
                        <h3 class="text-secondary">{{ $data['total_leads'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Published Content') }}</h6>
                        <h3 class="text-info">{{ $data['total_content'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Live Event Promos') }}</h6>
                        <h3 class="text-primary">{{ $data['total_event_promos'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Recent Applications') }}</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover m-0">
                            <thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Platform') }}</th><th>{{ translate('Status') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse($data['recent_applications'] as $app)
                                <tr>
                                    <td>{{ $app->creator_name }}</td>
                                    <td>{{ $app->platform ?? 'N/A' }}</td>
                                    <td><span class="badge badge-{{ $app->status == 'approved' ? 'success' : ($app->status == 'rejected' ? 'danger' : 'warning') }}">{{ $app->status }}</span></td>
                                    <td><a href="{{ route('admin.urban-goodz.creator.applications.show', $app->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center">{{ translate('No applications') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Top Creators') }}</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-sm table-hover m-0">
                            <thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Handle') }}</th><th>{{ translate('Earned') }}</th><th></th></tr></thead>
                            <tbody>
                            @forelse($data['top_creators'] as $creator)
                                <tr>
                                    <td>{{ $creator->display_name }}</td>
                                    <td>@ {{ $creator->handle }}</td>
                                    <td>{{\App\CentralLogics\Helpers::format_currency($creator->total_earned ?? 0)}}</td>
                                    <td><a href="{{ route('admin.urban-goodz.creator.profiles.show', $creator->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center">{{ translate('No approved creators') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
