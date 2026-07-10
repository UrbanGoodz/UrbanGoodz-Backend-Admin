@extends('layouts.admin.app')

@section('title', translate('Recommendation #') . $recommendation->id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <a href="{{ route('admin.urban-goodz.ai-copilot.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Recommendations') }}
            </a>
            <h1 class="page-header-title">{{ translate('Recommendation #') }}{{ $recommendation->id }}</h1>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted">{{ translate('Type') }}</small>
                        <p class="fw-bold">{{ ucwords(str_replace('_', ' ', $recommendation->recommendation_type)) }}
                            @if($recommendation->recommendation_subtype)
                                <span class="badge badge-soft-info">{{ str_replace('_', ' ', $recommendation->recommendation_subtype) }}</span>
                            @endif
                        </p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">{{ translate('Confidence') }}</small>
                        <p class="fw-bold">{{ $recommendation->confidence_score ? number_format($recommendation->confidence_score * 100) . '%' : 'N/A' }}</p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">{{ translate('Status') }}</small>
                        <p>@php $sMap = ['pending' => 'warning', 'accepted' => 'success', 'dismissed' => 'secondary', 'expired' => 'danger']; @endphp
                            <span class="badge badge-soft-{{ $sMap[$recommendation->status] ?? 'secondary' }}">{{ ucfirst($recommendation->status) }}</span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ translate('Suggested Action') }}</small>
                        <p class="fw-bold">{{ $recommendation->suggested_action }}</p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">{{ translate('Related Entity') }}</small>
                        <p>
                            @if($recommendation->relatable_type && $recommendation->relatable_id)
                                <code>{{ class_basename($recommendation->relatable_type) }} #{{ $recommendation->relatable_id }}</code>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                            @if($recommendation->order_id) &middot; <code>Order #{{ $recommendation->order_id }}</code> @endif
                            @if($recommendation->package_id) &middot; <code>Package #{{ $recommendation->package_id }}</code> @endif
                            @if($recommendation->route_id) &middot; <code>Route #{{ $recommendation->route_id }}</code> @endif
                            @if($recommendation->request_id) &middot; <code>Request #{{ $recommendation->request_id }}</code> @endif
                        </p>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">{{ translate('Reason') }}</small>
                        <p>{{ $recommendation->reason }}</p>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">{{ translate('Created') }}</small>
                        <p>{{ $recommendation->created_at->format('M d, Y h:i A') }}</p>
                    </div>
                    @if($recommendation->reviewed_by)
                    <div class="col-6">
                        <small class="text-muted">{{ translate('Reviewed By') }}</small>
                        <p>{{ $recommendation->reviewer?->name ?? 'Admin #' . $recommendation->reviewed_by }}
                            @if($recommendation->reviewed_at) &middot; {{ $recommendation->reviewed_at->format('M d, Y h:i A') }} @endif
                        </p>
                    </div>
                    @endif
                    @if($recommendation->admin_notes)
                    <div class="col-12">
                        <small class="text-muted">{{ translate('Admin Notes') }}</small>
                        <p>{{ $recommendation->admin_notes }}</p>
                    </div>
                    @endif
                    @if($recommendation->metadata)
                    <div class="col-12">
                        <small class="text-muted">{{ translate('Metadata') }}</small>
                        <pre class="bg-light p-2 rounded" style="font-size: 0.85rem;">{{ json_encode($recommendation->metadata, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($recommendation->status === 'pending')
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.accept', $recommendation->id) }}" class="d-inline">
                @csrf
                <div class="input-group">
                    <input type="text" name="admin_notes" class="form-control" placeholder="{{ translate('Optional notes...') }}">
                    <button type="submit" class="btn btn-success" onclick="return confirm('Accept this recommendation?')">
                        <i class="tio-checkmark-circle"></i> {{ translate('Accept') }}
                    </button>
                </div>
            </form>
            <form method="POST" action="{{ route('admin.urban-goodz.ai-copilot.dismiss', $recommendation->id) }}" class="d-inline">
                @csrf
                <div class="input-group">
                    <input type="text" name="admin_notes" class="form-control" placeholder="{{ translate('Optional notes...') }}">
                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Dismiss this recommendation?')">
                        <i class="tio-clear"></i> {{ translate('Dismiss') }}
                    </button>
                </div>
            </form>
        </div>
        @endif
    </div>
@endsection
