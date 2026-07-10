@extends('layouts.admin.app')

@section('title', translate('Creator Event Promotions'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('Creator Event Promotions') }}</h1></div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                            <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>{{ translate('Submitted') }}</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                            <option value="live" {{ request('status') == 'live' ? 'selected' : '' }}>{{ translate('Live') }}</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ translate('Completed') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="promo_type" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="social_post" {{ request('promo_type') == 'social_post' ? 'selected' : '' }}>{{ translate('Social Post') }}</option>
                            <option value="reel" {{ request('promo_type') == 'reel' ? 'selected' : '' }}>{{ translate('Reel') }}</option>
                            <option value="review" {{ request('promo_type') == 'review' ? 'selected' : '' }}>{{ translate('Review') }}</option>
                            <option value="booth_promo" {{ request('promo_type') == 'booth_promo' ? 'selected' : '' }}>{{ translate('Booth Promo') }}</option>
                            <option value="recap" {{ request('promo_type') == 'recap' ? 'selected' : '' }}>{{ translate('Recap') }}</option>
                            <option value="live_stream" {{ request('promo_type') == 'live_stream' ? 'selected' : '' }}>{{ translate('Live Stream') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Event') }}</th><th>{{ translate('Creator') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Commission') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($promotions as $p)
                        <tr>
                            <td>{{ $p->event->title ?? 'N/A' }}</td>
                            <td>{{ $p->profile->display_name ?? 'N/A' }}</td>
                            <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', $p->promo_type) }}</span></td>
                            <td>{{\App\CentralLogics\Helpers::format_currency($p->commission_earned)}}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.urban-goodz.creator.event-promotions.status', $p->id) }}" class="d-inline">
                                    @csrf
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="draft" {{ $p->status == 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                                        <option value="submitted" {{ $p->status == 'submitted' ? 'selected' : '' }}>{{ translate('Submitted') }}</option>
                                        <option value="approved" {{ $p->status == 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                                        <option value="live" {{ $p->status == 'live' ? 'selected' : '' }}>{{ translate('Live') }}</option>
                                        <option value="completed" {{ $p->status == 'completed' ? 'selected' : '' }}>{{ translate('Completed') }}</option>
                                    </select>
                                </form>
                            </td>
                            <td>{{ $p->created_at->format('M d, Y') }}</td>
                            <td><button type="button" class="btn btn-sm btn-ghost-info" data-toggle="modal" data-target="#promoModal{{ $p->id }}"><i class="tio-edit"></i></button></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center">{{ translate('No promotions found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($promotions->hasPages())
                <div class="card-footer">{{ $promotions->links() }}</div>
            @endif
        </div>
    </div>

    @foreach($promotions as $p)
        <div class="modal fade" id="promoModal{{ $p->id }}">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('admin.urban-goodz.creator.event-promotions.status', $p->id) }}">
                    @csrf
                    <div class="modal-header"><h5>{{ translate('Promotion Detail') }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                    <div class="modal-body">
                        <p><strong>{{ translate('Event') }}:</strong> {{ $p->event->title ?? 'N/A' }}</p>
                        <p><strong>{{ translate('Creator') }}:</strong> {{ $p->profile->display_name ?? 'N/A' }}</p>
                        <p><strong>{{ translate('Type') }}:</strong> {{ str_replace('_', ' ', $p->promo_type) }}</p>
                        @if($p->ticket_link)<p><strong>{{ translate('Ticket Link') }}:</strong> <a href="{{ $p->ticket_link }}" target="_blank">{{ $p->ticket_link }}</a></p>@endif
                        @if($p->vendor_booth_name)<p><strong>{{ translate('Vendor Booth') }}:</strong> {{ $p->vendor_booth_name }}</p>@endif
                        <div class="form-group">
                            <label>{{ translate('Admin Notes') }}</label>
                            <textarea name="admin_notes" class="form-control" rows="3">{{ $p->admin_notes }}</textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="status" value="{{ $p->status }}">
                        <button type="submit" class="btn btn-primary">{{ translate('Save Notes') }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    </div>
                </form>
            </div></div>
        </div>
    @endforeach
@endsection
