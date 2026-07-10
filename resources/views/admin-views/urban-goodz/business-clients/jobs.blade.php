@extends('layouts.admin.app')

@section('title', $client->company_name . ' ' . translate('Jobs'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $client->company_name }} {{ translate('Jobs') }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.business-clients.show', $client->id) }}" class="btn btn--secondary">{{ translate('Back to Client') }}</a>
            </div>
        </div>

        @php
            $statusBadgeMap = [
                'submitted' => 'badge-soft-info', 'under_review' => 'badge-soft-warning',
                'accepted' => 'badge-soft-primary', 'quoted' => 'badge-soft-warning',
                'quote_accepted' => 'badge-soft-primary', 'assigned' => 'badge-soft-info',
                'driver_en_route' => 'badge-soft-info', 'picked_up' => 'badge-soft-warning',
                'in_transit' => 'badge-soft-warning', 'delayed' => 'badge-soft-danger',
                'delivered' => 'badge-soft-success', 'completed' => 'badge-soft-success',
                'invoiced' => 'badge-soft-dark', 'paid' => 'badge-soft-success',
                'canceled' => 'badge-soft-secondary',
            ];
        @endphp

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Job #') }}</th>
                            <th>{{ translate('Type') }}</th>
                            <th>{{ translate('Pickup') }}</th>
                            <th>{{ translate('Dropoff') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($jobs as $key => $j)
                            <tr>
                                <td>{{ $jobs->firstItem() + $key }}</td>
                                <td class="font-weight-bold">{{ $j->job_number }}</td>
                                <td><span class="badge badge-soft-info">{{ str_replace('_', ' ', $j->job_type) }}</span></td>
                                <td>{{ $j->pickup_contact_name ?? translate('N/A') }}</td>
                                <td>{{ $j->dropoff_contact_name ?? translate('N/A') }}</td>
                                <td>{{ $j->quoted_amount ? '$' . number_format($j->quoted_amount, 2) : ($j->rate_offered ? '$' . number_format($j->rate_offered, 2) : '—') }}</td>
                                <td><span class="badge {{ $statusBadgeMap[$j->status] ?? 'badge-soft-info' }}">{{ str_replace('_', ' ', $j->status) }}</span></td>
                                <td>{{ $j->created_at->format('M d, Y') }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.business-clients.job-show', [$client->id, $j->id]) }}" class="btn btn-sm btn-outline--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($jobs) === 0)
                            <tr><td colspan="9" class="text-center">{{ translate('No jobs found') }}</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $jobs->links() }}</div>
        </div>
    </div>
@endsection
