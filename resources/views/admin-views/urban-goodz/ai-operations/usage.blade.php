@extends('layouts.admin.app')

@section('title', translate('AI Usage & Cost Stats'))

@push('css_or_js')
<style>
    .stat-card { background: #f8f9fa; border-radius: 8px; }
    .stat-number { font-size: 1.5rem; font-weight: 700; }
</style>
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-operations.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to AI Operations') }}
            </a>
            <h1 class="page-header-title">{{ translate('AI Usage & Cost Statistics') }}</h1>
        </div>

        {{-- Conversation Stats --}}
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('AI Concierge Conversations') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3 col-6">
                        <div class="stat-card p-3 text-center">
                            <div class="stat-number text--primary">{{ number_format($conversationStats['total']) }}</div>
                            <small class="text-muted">{{ translate('Total All Time') }}</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card p-3 text-center">
                            <div class="stat-number text--info">{{ number_format($conversationStats['today']) }}</div>
                            <small class="text-muted">{{ translate('Today') }}</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card p-3 text-center">
                            <div class="stat-number text--secondary">{{ number_format($conversationStats['this_week']) }}</div>
                            <small class="text-muted">{{ translate('This Week') }}</small>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card p-3 text-center">
                            <div class="stat-number text--warning">{{ number_format($conversationStats['this_month']) }}</div>
                            <small class="text-muted">{{ translate('This Month') }}</small>
                        </div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-soft-success">{{ number_format($conversationStats['resolved']) }}</span>
                            <small>{{ translate('Resolved') }}</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-soft-warning">{{ number_format($conversationStats['pending']) }}</span>
                            <small>{{ translate('Pending') }}</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge badge-soft-danger">{{ number_format($conversationStats['escalated']) }}</span>
                            <small>{{ translate('Escalated') }}</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            {{-- Action Stats --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('AI Actions Executed') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number text--primary">{{ number_format($actionStats['total']) }}</div>
                                    <small class="text-muted">{{ translate('Total') }}</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number text--info">{{ number_format($actionStats['today']) }}</div>
                                    <small class="text-muted">{{ translate('Today') }}</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number text--secondary">{{ number_format($actionStats['this_week']) }}</div>
                                    <small class="text-muted">{{ translate('This Week') }}</small>
                                </div>
                            </div>
                        </div>
                        <hr>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-soft-success">{{ number_format($actionStats['auto_executed']) }}</span>
                                    <small>{{ translate('Auto-Executed') }}</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge badge-soft-primary">{{ number_format($actionStats['human_approved']) }}</span>
                                    <small>{{ translate('Human Approved') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Recommendation Stats --}}
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Recommendations Overview') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number text--warning">{{ number_format($recommendationStats['pending']) }}</div>
                                    <small class="text-muted">{{ translate('Pending') }}</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number text--success">{{ number_format($recommendationStats['accepted']) }}</div>
                                    <small class="text-muted">{{ translate('Accepted') }}</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="text-center">
                                    <div class="stat-number text--secondary">{{ number_format($recommendationStats['dismissed']) }}</div>
                                    <small class="text-muted">{{ translate('Dismissed') }}</small>
                                </div>
                            </div>
                        </div>
                        @if(!empty($recommendationStats['by_type']))
                        <hr>
                        <small class="text-muted d-block mb-2">{{ translate('By Type') }}</small>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($recommendationStats['by_type'] as $type => $count)
                            <span class="badge badge-soft-info">{{ ucwords(str_replace('_', ' ', $type)) }}: {{ $count }}</span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Activity Charts (text-based since no chart library guaranteed) --}}
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Conversations (Last 30 Days)') }}</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        @forelse($dailyConversations as $day)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</small>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: {{ min($day->count * 3, 200) }}px; height: 12px; background: #6f42c1; border-radius: 2px;"></div>
                                <small class="fw-bold">{{ $day->count }}</small>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted text-center py-3">{{ translate('No conversation data yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ translate('Actions (Last 30 Days)') }}</h5>
                    </div>
                    <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                        @forelse($dailyActions as $day)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                            <small>{{ \Carbon\Carbon::parse($day->date)->format('M d') }}</small>
                            <div class="d-flex align-items-center gap-2">
                                <div style="width: {{ min($day->count * 3, 200) }}px; height: 12px; background: #fd7e14; border-radius: 2px;"></div>
                                <small class="fw-bold">{{ $day->count }}</small>
                            </div>
                        </div>
                        @empty
                        <p class="text-muted text-center py-3">{{ translate('No action data yet.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
