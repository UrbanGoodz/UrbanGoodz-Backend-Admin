@extends('layouts.admin.app')

@section('title', translate('Creator Reports'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('Creator Reports') }}</h1></div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-2">
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="performance" {{ $type == 'performance' ? 'selected' : '' }}>{{ translate('Creator Performance') }}</option>
                            <option value="revenue" {{ $type == 'revenue' ? 'selected' : '' }}>{{ translate('Creator Revenue') }}</option>
                            <option value="campaign" {{ $type == 'campaign' ? 'selected' : '' }}>{{ translate('Campaign Report') }}</option>
                            <option value="leads" {{ $type == 'leads' ? 'selected' : '' }}>{{ translate('Lead Report') }}</option>
                            <option value="payout" {{ $type == 'payout' ? 'selected' : '' }}>{{ translate('Payout Report') }}</option>
                            <option value="event-promo" {{ $type == 'event-promo' ? 'selected' : '' }}>{{ translate('Event Promo Report') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ $dateFrom->format('Y-m-d') }}"></div>
                    <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ $dateTo->format('Y-m-d') }}"></div>
                    <div class="col-md-2">
                        <select name="creator_id" class="form-control">
                            <option value="">{{ translate('All Creators') }}</option>
                            @foreach($creators as $c)
                                <option value="{{ $c->id }}" {{ $creatorId == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i> {{ translate('Generate') }}</button></div>
                    <div class="col-md-2">
                        <button onclick="window.print()" class="btn btn-secondary"><i class="tio-print"></i> {{ translate('Print') }}</button>
                    </div>
                </form>
            </div>

            <div class="card-body">
                @if($type == 'performance')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-3"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Creators') }}</h6><h4>{{ $reportData['totals']['total_creators'] }}</h4></div></div></div>
                        <div class="col-sm-3"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Content') }}</h6><h4>{{ $reportData['totals']['total_content'] }}</h4></div></div></div>
                        <div class="col-sm-3"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Campaigns') }}</h6><h4>{{ $reportData['totals']['total_campaigns'] }}</h4></div></div></div>
                        <div class="col-sm-3"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Earned') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['total_earned'])}}</h4></div></div></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>{{ translate('Creator') }}</th><th>{{ translate('Handle') }}</th><th>{{ translate('Content') }}</th><th>{{ translate('Campaigns') }}</th><th>{{ translate('Leads') }}</th><th>{{ translate('Earned') }}</th></tr></thead>
                        <tbody>
                        @foreach($reportData['rows'] as $r)
                            <tr><td>{{ $r->display_name }}</td><td>@ {{ $r->handle }}</td><td>{{ $r->content_count }}</td><td>{{ $r->campaigns_count }}</td><td>{{ $r->leads_count }}</td><td>{{\App\CentralLogics\Helpers::format_currency($r->earnings_sum_amount ?? 0)}}</td></tr>
                        @endforeach
                        </tbody>
                    </table>

                @elseif($type == 'revenue')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Pending') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['total_pending'])}}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Approved') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['total_approved'])}}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Paid') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['total_paid'])}}</h4></div></div></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>{{ translate('Creator') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Source') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th></tr></thead>
                        <tbody>
                        @foreach($reportData['rows'] as $r)
                            <tr><td>{{ $r->profile->display_name ?? 'N/A' }}</td><td>{{ $r->type }}</td><td>{{\App\CentralLogics\Helpers::format_currency($r->amount)}}</td><td>{{ $r->source_type ?? 'N/A' }}</td><td><span class="badge badge-{{ $r->status == 'paid' ? 'success' : 'warning' }}">{{ $r->status }}</span></td><td>{{ $r->created_at->format('M d, Y') }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>

                @elseif($type == 'campaign')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Campaigns') }}</h6><h4>{{ $reportData['totals']['total_campaigns'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Assignments') }}</h6><h4>{{ $reportData['totals']['total_assignments'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Payout') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['total_payout'])}}</h4></div></div></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>{{ translate('Campaign') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Assignments') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Payout') }}</th></tr></thead>
                        <tbody>
                        @foreach($reportData['rows'] as $r)
                            <tr><td>{{ $r->title }}</td><td>{{ str_replace('_', ' ', $r->type) }}</td><td>{{ $r->assignments_count }}</td><td><span class="badge badge-soft-info">{{ $r->status }}</span></td><td>{{\App\CentralLogics\Helpers::format_currency($r->earnings_sum_amount ?? 0)}}</td></tr>
                        @endforeach
                        </tbody>
                    </table>

                @elseif($type == 'leads')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Leads') }}</h6><h4>{{ $reportData['totals']['total'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Onboarded') }}</h6><h4>{{ $reportData['totals']['onboarded'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('New') }}</h6><h4>{{ $reportData['totals']['new'] }}</h4></div></div></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>{{ translate('Business') }}</th><th>{{ translate('Category') }}</th><th>{{ translate('City') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Module') }}</th></tr></thead>
                        <tbody>
                        @foreach($reportData['rows'] as $r)
                            <tr><td>{{ $r->business_name }}</td><td>{{ $r->category ?? 'N/A' }}</td><td>{{ $r->city ?? 'N/A' }}</td><td><span class="badge badge-soft-info">{{ $r->status }}</span></td><td>{{ $r->suggested_module ?? 'N/A' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>

                @elseif($type == 'payout')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Payouts') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['total_payouts'])}}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Approved') }}</h6><h4>{{ $reportData['totals']['approved_count'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Paid') }}</h6><h4>{{ $reportData['totals']['paid_count'] }}</h4></div></div></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>{{ translate('Creator') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th></tr></thead>
                        <tbody>
                        @foreach($reportData['rows'] as $r)
                            <tr><td>{{ $r->profile->display_name ?? 'N/A' }}</td><td>{{\App\CentralLogics\Helpers::format_currency($r->amount)}}</td><td><span class="badge badge-{{ $r->status == 'paid' ? 'success' : 'info' }}">{{ $r->status }}</span></td><td>{{ $r->created_at->format('M d, Y') }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>

                @elseif($type == 'event-promo')
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Total Promotions') }}</h6><h4>{{ $reportData['totals']['total'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Live') }}</h6><h4>{{ $reportData['totals']['live'] }}</h4></div></div></div>
                        <div class="col-sm-4"><div class="card bg-light"><div class="card-body"><h6>{{ translate('Commission') }}</h6><h4>{{\App\CentralLogics\Helpers::format_currency($reportData['totals']['commission'])}}</h4></div></div></div>
                    </div>
                    <table class="table table-sm">
                        <thead><tr><th>{{ translate('Event') }}</th><th>{{ translate('Creator') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Commission') }}</th></tr></thead>
                        <tbody>
                        @foreach($reportData['rows'] as $r)
                            <tr><td>{{ $r->event->title ?? 'N/A' }}</td><td>{{ $r->profile->display_name ?? 'N/A' }}</td><td>{{ str_replace('_', ' ', $r->promo_type) }}</td><td><span class="badge badge-soft-info">{{ $r->status }}</span></td><td>{{\App\CentralLogics\Helpers::format_currency($r->commission_earned)}}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>

    <style media="print">
        .page-header, .card-header form .btn, footer, .sidebar { display: none; }
        .card { border: none; box-shadow: none; }
        .table { font-size: 10px; }
        body { background: white; }
    </style>
@endsection
