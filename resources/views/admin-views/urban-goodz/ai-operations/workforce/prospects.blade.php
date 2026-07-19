@extends('layouts.admin.app')

@section('title', translate('Merchant Prospects'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title">{{ translate('Merchant Prospects') }}</h1>
                <p class="text-muted">{{ translate('Review AI-generated merchant outreach prospects and campaign status.') }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.ai-operations.workforce.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-back"></i> {{ translate('Workforce Overview') }}
                </a>
                <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline-secondary">
                    <i class="tio-arrow-back"></i> {{ translate('AI Operations') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Business Name') }}</th>
                                <th>{{ translate('Category') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Campaign') }}</th>
                                <th>{{ translate('Contact') }}</th>
                                <th>{{ translate('Messages') }}</th>
                                <th>{{ translate('Last Contacted') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prospects as $prospect)
                                <tr>
                                    <td>{{ $prospect->business_name }}</td>
                                    <td>{{ $prospect->category ?? '-' }}</td>
                                    <td>{{ ucfirst($prospect->prospect_status) }}</td>
                                    <td>{{ ucfirst($prospect->campaign_status) }}</td>
                                    <td>{{ $prospect->public_email ?? $prospect->public_phone ?? translate('N/A') }}</td>
                                    <td>{{ $prospect->outreach_messages_count }}</td>
                                    <td>{{ $prospect->last_contacted_at?->diffForHumans() ?? translate('N/A') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">{{ translate('No merchant prospects found.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="mt-3">
            {{ $prospects->links() }}
        </div>
    </div>
@endsection
