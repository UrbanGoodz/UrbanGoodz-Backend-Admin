@extends('layouts.admin.app')

@section('title', translate('Package') . ' ' . $package->tracking_id)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h1 class="page-header-title mb-0">{{ translate('Package') }}: {{ $package->tracking_id }}</h1>
                <p class="text-muted mb-0">
                    @if($package->barcode)
                    <code>{{ $package->barcode }}</code> &middot;
                    @endif
                    {{ translate('Status') }}: <span class="badge badge-soft-info">{{ $package->status }}</span>
                </p>
            </div>
            <a href="{{ route('admin.urban-goodz.age-compliance.packages') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back') }}
            </a>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Age Compliance Flags') }}</h5></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge {{ $package->age_restricted ? 'badge-soft-warning' : 'badge-soft-secondary' }}">
                                {{ translate('Age Restricted') }}: {{ $package->age_restricted ? 'Yes' : 'No' }}
                            </span>
                            <span class="badge {{ $package->requires_id_verification ? 'badge-soft-info' : 'badge-soft-secondary' }}">
                                {{ translate('Requires ID') }}: {{ $package->requires_id_verification ? 'Yes' : 'No' }}
                            </span>
                            <span class="badge {{ $package->no_contactless_delivery ? 'badge-soft-warning' : 'badge-soft-secondary' }}">
                                {{ translate('No Contactless') }}: {{ $package->no_contactless_delivery ? 'Yes' : 'No' }}
                            </span>
                            <span class="badge {{ $package->signature_required ? 'badge-soft-info' : 'badge-soft-secondary' }}">
                                {{ translate('Signature Required') }}
                            </span>
                            <span class="badge {{ $package->delivery_completion_locked_until_verified ? 'badge-soft-danger' : 'badge-soft-secondary' }}">
                                {{ translate('Delivery Locked') }}: {{ $package->delivery_completion_locked_until_verified ? 'Yes' : 'No' }}
                            </span>
                            <span class="badge {{ $package->admin_review_required_on_failure ? 'badge-soft-warning' : 'badge-soft-secondary' }}">
                                {{ translate('Admin Review on Failure') }}: {{ $package->admin_review_required_on_failure ? 'Yes' : 'No' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted d-block">{{ translate('Verification Status') }}</small>
                            @php $vMap = ['pending' => 'warning', 'verified' => 'success', 'failed' => 'danger', 'refused' => 'danger']; @endphp
                            <span class="badge badge-soft-{{ $vMap[$package->age_verification_status] ?? 'secondary' }}" style="font-size: 0.9rem;">
                                {{ ucfirst($package->age_verification_status ?? 'Not verified') }}
                            </span>
                        </div>
                        @if($package->age_verification_refusal_reason)
                        <div class="mt-2">
                            <small class="text-muted d-block">{{ translate('Refusal Reason') }}</small>
                            <span class="badge badge-soft-danger">{{ str_replace('_', ' ', $package->age_verification_refusal_reason) }}</span>
                        </div>
                        @endif
                        @if($package->age_verification_driver_notes)
                        <div class="mt-2">
                            <small class="text-muted d-block">{{ translate('Driver Notes') }}</small>
                            <p class="mb-0">{{ $package->age_verification_driver_notes }}</p>
                        </div>
                        @endif
                        @if($package->age_verified_at)
                        <div class="mt-2">
                            <small class="text-muted d-block">{{ translate('Verified At') }}</small>
                            <strong>{{ $package->age_verified_at->format('M d, Y h:i A') }}</strong>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Linked Records') }}</h5></div>
                    <div class="card-body">
                        @if($package->route)
                        <small class="text-muted d-block">{{ translate('Route') }}: <a href="#">{{ $package->route->route_name ?? '#' . $package->dedicated_route_id }}</a></small>
                        @endif
                        @if($package->manifest)
                        <small class="text-muted d-block">{{ translate('Manifest') }}: <a href="{{ route('admin.urban-goodz.manifests.show', $package->manifest_id) }}">{{ $package->manifest->manifest_name ?? '#' . $package->manifest_id }}</a></small>
                        @endif
                        @if($package->client)
                        <small class="text-muted d-block">{{ translate('Client') }}: {{ $package->client->company_name ?? '' }}</small>
                        @endif
                        <small class="text-muted d-block">{{ translate('Dropoff') }}: {{ $package->dropoff_address ?? '-' }}</small>
                    </div>
                </div>

                @if($package->ageVerifications->count() > 0)
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ translate('Verification History') }} ({{ $package->ageVerifications->count() }})</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Driver') }}</th>
                                        <th>{{ translate('Attempted') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($package->ageVerifications as $av)
                                    <tr>
                                        <td>{{ $av->id }}</td>
                                        <td><span class="badge badge-soft-{{ $vMap[$av->verification_status] ?? 'secondary' }}">{{ ucfirst($av->verification_status) }}</span></td>
                                        <td>{{ $av->driver?->f_name ?? '' }} {{ $av->driver?->l_name ?? '' }}</td>
                                        <td><small>{{ $av->verification_attempted_at?->format('M d, h:i A') ?? '-' }}</small></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
