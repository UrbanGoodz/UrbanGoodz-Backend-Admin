@extends('layouts.admin.app')

@section('title', translate('Creator Earnings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('Creator Earnings') }}</h1></div>

        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Pending') }}</h6>
                        <h3 class="text-warning">{{\App\CentralLogics\Helpers::format_currency($totals['pending'])}}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Approved') }}</h6>
                        <h3 class="text-info">{{\App\CentralLogics\Helpers::format_currency($totals['approved'])}}</h3>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Paid') }}</h6>
                        <h3 class="text-success">{{\App\CentralLogics\Helpers::format_currency($totals['paid'])}}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>{{ translate('Paid') }}</option>
                            <option value="refunded" {{ request('status') == 'refunded' ? 'selected' : '' }}>{{ translate('Refunded') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="type" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="commission" {{ request('type') == 'commission' ? 'selected' : '' }}>{{ translate('Commission') }}</option>
                            <option value="flat_fee" {{ request('type') == 'flat_fee' ? 'selected' : '' }}>{{ translate('Flat Fee') }}</option>
                            <option value="bonus" {{ request('type') == 'bonus' ? 'selected' : '' }}>{{ translate('Bonus') }}</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="creator_id" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Creators') }}</option>
                            @foreach($creators as $c)
                                <option value="{{ $c->id }}" {{ request('creator_id') == $c->id ? 'selected' : '' }}>{{ $c->display_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2"><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" placeholder="{{ translate('From') }}"></div>
                    <div class="col-md-2"><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" placeholder="{{ translate('To') }}"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Creator') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Amount') }}</th><th>{{ translate('Source') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($earnings as $e)
                        <tr>
                            <td>{{ $e->profile->display_name ?? $e->application->creator_name ?? 'N/A' }}</td>
                            <td>{{ str_replace('_', ' ', $e->type) }}</td>
                            <td>{{\App\CentralLogics\Helpers::format_currency($e->amount)}}</td>
                            <td>{{ $e->source_type ?? 'N/A' }}</td>
                            <td><span class="badge badge-{{ $e->status == 'paid' ? 'success' : ($e->status == 'pending' ? 'warning' : 'info') }}">{{ $e->status }}</span></td>
                            <td>{{ $e->created_at->format('M d, Y') }}</td>
                            <td>
                                @if($e->status == 'pending')
                                    <a href="{{ route('admin.urban-goodz.creator.earnings.approve', $e->id) }}" class="btn btn-sm btn-success" onclick="return confirm('{{ translate('Approve this earning for payout?') }}')">{{ translate('Approve') }}</a>
                                @elseif($e->status == 'approved')
                                    <a href="{{ route('admin.urban-goodz.creator.earnings.paid', $e->id) }}" class="btn btn-sm btn-info" onclick="return confirm('{{ translate('Mark as paid?') }}')">{{ translate('Mark Paid') }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">{{ translate('No earnings found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($earnings->hasPages())
                <div class="card-footer">{{ $earnings->links() }}</div>
            @endif
        </div>
    </div>
@endsection
