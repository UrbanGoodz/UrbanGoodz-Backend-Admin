@extends('layouts.admin.app')

@section('title', translate('Age Verification') . ' #' . $verification->id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title mb-0">{{ translate('Age Verification') }} #{{ $verification->id }}</h1>
                <p class="text-muted mb-0">
                    {{ translate('Verification Status') }}:
                    @php $vMap = ['pending' => 'warning', 'verified' => 'success', 'failed' => 'danger', 'refused' => 'danger']; @endphp
                    <span class="badge badge-soft-{{ $vMap[$verification->verification_status] ?? 'secondary' }}">{{ ucfirst($verification->verification_status) }}</span>
                </p>
            </div>
            <a href="{{ route('admin.urban-goodz.age-compliance.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back') }}
            </a>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Verification Details') }}</h5></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Package') }}</small>
                                <strong>{{ $verification->package?->tracking_id ?? $verification->package?->barcode ?? '-' }}</strong>
                                @if($verification->package?->route)
                                <br><small class="text-muted">{{ translate('Route') }}: {{ $verification->package->route->route_name ?? '-' }}</small>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Order') }}</small>
                                @if($verification->order_id)
                                <strong>{{ translate('Order') }} #{{ $verification->order_id }}</strong>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Driver') }}</small>
                                <strong>{{ $verification->driver?->f_name ?? '' }} {{ $verification->driver?->l_name ?? '' }}</strong>
                                <br><small class="text-muted">{{ $verification->driver?->phone ?? '' }}</small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Verification Attempted At') }}</small>
                                <strong>{{ $verification->verification_attempted_at?->format('M d, Y h:i A') ?? '-' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('ID Type Checked') }}</small>
                                <strong>{{ $verification->id_type_checked ?? translate('Not recorded') }}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Recipient Name Verified') }}</small>
                                <strong>{{ $verification->recipient_name_verified ?? '-' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Recipient DOB Verified') }}</small>
                                <strong>{{ $verification->recipient_dob_verified?->format('M d, Y') ?? '-' }}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Age Confirmed') }}</small>
                                <strong>{!! $verification->recipient_age_confirmed ? '<span class="badge badge-soft-success">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Signature Captured') }}</small>
                                <strong>{!! $verification->signature_captured ? '<span class="badge badge-soft-success">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">{{ translate('Proof Photo Captured') }}</small>
                                <strong>{!! $verification->proof_photo_captured ? '<span class="badge badge-soft-success">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</strong>
                            </div>
                            @if($verification->refusal_reason)
                            <div class="col-12">
                                <small class="text-muted d-block">{{ translate('Refusal Reason') }}</small>
                                <span class="badge badge-soft-danger" style="font-size: 0.85rem;">{{ str_replace('_', ' ', $verification->refusal_reason) }}</span>
                            </div>
                            @endif
                            @if($verification->driver_notes)
                            <div class="col-12">
                                <small class="text-muted d-block">{{ translate('Driver Notes') }}</small>
                                <p class="mb-0">{{ $verification->driver_notes }}</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Admin Review') }}</h5></div>
                    <div class="card-body">
                        @if($verification->admin_review_required)
                        <div class="mb-3">
                            <span class="badge badge-soft-warning mb-2">{{ translate('Review Required') }}</span>
                            <p class="small text-muted mb-1">{{ translate('Current Status') }}:
                                @php $aMap = ['pending' => 'warning', 'reviewed' => 'info', 'resolved' => 'success', 'escalated' => 'danger']; @endphp
                                <span class="badge badge-soft-{{ $aMap[$verification->admin_review_status] ?? 'warning' }}">{{ ucfirst($verification->admin_review_status ?? 'pending') }}</span>
                            </p>
                        </div>
                        @endif

                        @if($verification->admin_notes)
                        <div class="mb-3">
                            <small class="text-muted d-block">{{ translate('Previous Notes') }}</small>
                            <p class="mb-0" style="white-space: pre-wrap;">{{ $verification->admin_notes }}</p>
                            @if($verification->reviewer)
                            <small class="text-muted">{{ translate('Reviewed by') }}: {{ $verification->reviewer?->f_name ?? '' }} {{ $verification->reviewer?->l_name ?? '' }} ({{ $verification->admin_reviewed_at?->format('M d, Y h:i A') ?? '' }})</small>
                            @endif
                        </div>
                        @endif

                        <form action="{{ route('admin.urban-goodz.age-compliance.review', $verification->id) }}" method="POST">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Review Status') }}</label>
                                    <select name="admin_review_status" class="form-control" required>
                                        <option value="reviewed" {{ $verification->admin_review_status === 'reviewed' ? 'selected' : '' }}>{{ translate('Reviewed') }}</option>
                                        <option value="resolved" {{ $verification->admin_review_status === 'resolved' ? 'selected' : '' }}>{{ translate('Resolved') }}</option>
                                        <option value="escalated" {{ $verification->admin_review_status === 'escalated' ? 'selected' : '' }}>{{ translate('Escalated') }}</option>
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label">{{ translate('Admin Notes') }}</label>
                                    <textarea name="admin_notes" class="form-control" rows="2" placeholder="{{ translate('Add review notes...') }}"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn--primary">{{ translate('Update Review') }}</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Package Info') }}</h5></div>
                    <div class="card-body">
                        @if($verification->package)
                        <small class="text-muted d-block">{{ translate('Tracking') }}: <code>{{ $verification->package->tracking_id }}</code></small>
                        <small class="text-muted d-block">{{ translate('Barcode') }}: <code>{{ $verification->package->barcode ?? '-' }}</code></small>
                        <small class="text-muted d-block">{{ translate('Status') }}: {{ $verification->package->status }}</small>
                        @if($verification->package->dropoff_address)
                        <small class="text-muted d-block">{{ translate('Dropoff') }}: {{ $verification->package->dropoff_address }}</small>
                        @endif
                        @else
                        <p class="text-muted mb-0">{{ translate('No package linked') }}</p>
                        @endif
                    </div>
                </div>

                @if($verification->order)
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Order Info') }}</h5></div>
                    <div class="card-body">
                        <small class="text-muted d-block">{{ translate('Order') }} #{{ $verification->order_id }}</small>
                        <small class="text-muted d-block">{{ translate('Amount') }}: ${{ number_format($verification->order->order_amount ?? 0, 2) }}</small>
                        <small class="text-muted d-block">{{ translate('Age Restricted') }}: {!! $verification->order->age_restricted_order ? '<span class="badge badge-soft-warning">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</small>
                    </div>
                </div>
                @endif

                @if($verification->route)
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Route Info') }}</h5></div>
                    <div class="card-body">
                        <small class="text-muted d-block">{{ translate('Route') }}: {{ $verification->route->route_name ?? '-' }}</small>
                        <small class="text-muted d-block">{{ translate('Type') }}: {{ $verification->route->route_type ?? '-' }}</small>
                        <small class="text-muted d-block">{{ translate('Contains Age Restricted') }}: {!! $verification->route->contains_age_restricted_items ? '<span class="badge badge-soft-warning">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</small>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
