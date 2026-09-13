@extends('layouts.admin.app')

@section('title', translate('Load Bids'))

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-gavel"></i></span>
                <span>{{ translate('Bids') }}</span>
                @if ($load)
                    <span class="text-muted fs-6 ms-2">{{ $load->load_number ?? ('#' . $load->id) }}</span>
                @endif
            </h1>
            <a href="{{ route('admin.urban-goodz.load-board.index') }}" class="btn btn-secondary btn-sm">
                {{ translate('Back to Load Board') }}
            </a>
        </div>

        @if ($load)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="text-muted fs-12">{{ translate('Status') }}</div>
                            <div class="text-capitalize">{{ str_replace('_', ' ', (string) $load->status) }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted fs-12">{{ translate('Pickup') }}</div>
                            <div>{{ $load->pickup_city ?? $load->pickup_location ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted fs-12">{{ translate('Dropoff') }}</div>
                            <div>{{ $load->dropoff_city ?? $load->dropoff_location ?? '-' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-muted fs-12">{{ translate('Offered') }}</div>
                            <div>{{ $load->rate ? \App\CentralLogics\Helpers::format_currency($load->rate) : '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    {{ translate('Bids Received') }}
                    <span class="badge badge-soft-dark ms-1">{{ count($bids ?? []) }}</span>
                </h5>
            </div>
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Driver') }}</th>
                            <th>{{ translate('Bid Amount') }}</th>
                            <th>{{ translate('Message') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Placed') }}</th>
                            <th class="text-center">{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($bids ?? []) as $key => $bid)
                            <tr>
                                <td>{{ $key + 1 }}</td>
                                <td>
                                    @if ($bid->driver)
                                        {{ $bid->driver->f_name }} {{ $bid->driver->l_name }}
                                    @else
                                        #{{ $bid->driver_id }}
                                    @endif
                                </td>
                                <td>{{ \App\CentralLogics\Helpers::format_currency($bid->bid_amount ?? 0) }}</td>
                                <td>{{ $bid->bid_message ? \Illuminate\Support\Str::limit($bid->bid_message, 50) : '-' }}</td>
                                <td>
                                    @php($status = (string) $bid->status)
                                    <span class="badge badge-soft-{{ $status === 'accepted' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}">
                                        {{ $status ?: translate('pending') }}
                                    </span>
                                </td>
                                <td>{{ $bid->created_at ? $bid->created_at->format('d M Y H:i') : '-' }}</td>
                                <td class="text-center">
                                    {{-- Only an undecided bid can still be accepted or rejected;
                                         once responded_at is set the decision is made. --}}
                                    @if (!$bid->responded_at && $status !== 'accepted' && $status !== 'rejected')
                                        <form class="d-inline" method="POST"
                                              action="{{ route('admin.urban-goodz.load-board.bid-accept', ['loadId' => $bid->load_id, 'bidId' => $bid->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn--primary">{{ translate('Accept') }}</button>
                                        </form>
                                        <form class="d-inline" method="POST"
                                              action="{{ route('admin.urban-goodz.load-board.bid-reject', ['loadId' => $bid->load_id, 'bidId' => $bid->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ translate('Reject') }}</button>
                                        </form>
                                    @else
                                        <span class="text-muted">{{ translate('decided') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">{{ translate('No bids on this load yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
