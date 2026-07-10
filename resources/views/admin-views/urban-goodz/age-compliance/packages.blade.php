@extends('layouts.admin.app')

@section('title', translate('Age-Restricted Packages'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Age-Restricted Packages') }}</h1>
            <a href="{{ route('admin.urban-goodz.age-compliance.index') }}" class="btn btn-outline--primary">
                <i class="tio-arrow-backward"></i> {{ translate('Back to Compliance') }}
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">{{ translate('Packages') }}</h5>
                <form method="GET">
                    <select name="verification_status" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Verification Statuses') }}</option>
                        @foreach(['pending', 'verified', 'failed', 'refused'] as $s)
                        <option value="{{ $s }}" {{ request('verification_status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>{{ translate('Tracking') }}</th>
                                <th>{{ translate('Barcode') }}</th>
                                <th>{{ translate('Age Restricted') }}</th>
                                <th>{{ translate('ID Required') }}</th>
                                <th>{{ translate('Locked') }}</th>
                                <th>{{ translate('Verification Status') }}</th>
                                <th>{{ translate('Refusal') }}</th>
                                <th>{{ translate('Verified By') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($packages as $pkg)
                            <tr>
                                <td>{{ $pkg->id }}</td>
                                <td><code>{{ $pkg->tracking_id }}</code></td>
                                <td><code>{{ $pkg->barcode ?? '-' }}</code></td>
                                <td>{!! $pkg->age_restricted ? '<span class="badge badge-soft-warning">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</td>
                                <td>{!! $pkg->requires_id_verification ? '<span class="badge badge-soft-info">Yes</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</td>
                                <td>{!! $pkg->delivery_completion_locked_until_verified ? '<span class="badge badge-soft-danger">Locked</span>' : '<span class="badge badge-soft-secondary">No</span>' !!}</td>
                                <td>
                                    @php $vMap = ['pending' => 'warning', 'verified' => 'success', 'failed' => 'danger', 'refused' => 'danger']; @endphp
                                    <span class="badge badge-soft-{{ $vMap[$pkg->age_verification_status] ?? 'secondary' }}">
                                        {{ ucfirst($pkg->age_verification_status ?? 'N/A') }}
                                    </span>
                                </td>
                                <td>
                                    @if($pkg->age_verification_refusal_reason)
                                    <small>{{ str_replace('_', ' ', $pkg->age_verification_refusal_reason) }}</small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td><small>{{ $pkg->ageVerifiedByDriver?->f_name ?? '' }} {{ $pkg->ageVerifiedByDriver?->l_name ?? '-' }}</small></td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.age-compliance.packages.show', $pkg->id) }}" class="btn btn-sm btn--primary">{{ translate('View') }}</a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">{{ translate('No age-restricted packages found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $packages->links() }}
            </div>
        </div>
    </div>
@endsection
