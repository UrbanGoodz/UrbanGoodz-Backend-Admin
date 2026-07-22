@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Sourced Loads'))

@section('content')
    <div class="content container-fluid">

        {{-- Sub-Navigation --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Overview') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-link"></i> {{ translate('Sources') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.search') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-search"></i> {{ translate('Search Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.saved-searches') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-save"></i> {{ translate('Saved Searches') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Recommendations') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-refresh"></i> {{ translate('Sync Runs') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-warning"></i> {{ translate('Errors') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.settings') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-settings-outlined"></i> {{ translate('Settings') }}
                    </a>
                </div>
            </div>
        </div>

        {{-- Breadcrumb & Header --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent p-0 mb-1">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">{{ translate('Admin') }}</a></li>
                        <li class="breadcrumb-item"><a href="#">{{ translate('AI Operations') }}</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}">{{ translate('Load Sourcing') }}</a></li>
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Sourced Loads') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Sourced Loads') }}</h1>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="d-flex flex-wrap gap-2 align-items-end">
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Status') }}</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>{{ translate('Available') }}</option>
                            <option value="assigned" {{ request('status') === 'assigned' ? 'selected' : '' }}>{{ translate('Assigned') }}</option>
                            <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>{{ translate('In Transit') }}</option>
                            <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>{{ translate('Delivered') }}</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                            <option value="pending_review" {{ request('status') === 'pending_review' ? 'selected' : '' }}>{{ translate('Pending Review') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Source') }}</label>
                        <select name="source" class="form-control form-control-sm">
                            <option value="">{{ translate('All Sources') }}</option>
                            @foreach($sources as $src)
                                <option value="{{ $src->source_key }}" {{ request('source') === $src->source_key ? 'selected' : '' }}>{{ $src->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Equipment') }}</label>
                        <select name="equipment_type" class="form-control form-control-sm">
                            <option value="">{{ translate('All Equipment') }}</option>
                            <option value="dry_van" {{ request('equipment_type') === 'dry_van' ? 'selected' : '' }}>{{ translate('Dry Van') }}</option>
                            <option value="reefer" {{ request('equipment_type') === 'reefer' ? 'selected' : '' }}>{{ translate('Reefer') }}</option>
                            <option value="flatbed" {{ request('equipment_type') === 'flatbed' ? 'selected' : '' }}>{{ translate('Flatbed') }}</option>
                            <option value="box_truck" {{ request('equipment_type') === 'box_truck' ? 'selected' : '' }}>{{ translate('Box Truck') }}</option>
                            <option value="power_only" {{ request('equipment_type') === 'power_only' ? 'selected' : '' }}>{{ translate('Power Only') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Date From') }}</label>
                        <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
                    </div>
                    <div>
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Date To') }}</label>
                        <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn--primary">
                            <i class="tio-filter"></i> {{ translate('Filter') }}
                        </button>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Clear') }}</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Bulk Actions --}}
        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.bulk-action') }}" id="bulkActionForm">
            @csrf
            <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="selectAll">
                            <label class="form-check-label" for="{{ translate('Select All') }}">{{ translate('Select All') }}</label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success" onclick="document.getElementById('bulkActionInput').value='approve'">
                            <i class="tio-checkmark-circle"></i> {{ translate('Bulk Approve') }}
                        </button>
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="document.getElementById('bulkActionInput').value='reject'">
                            <i class="tio-clear"></i> {{ translate('Bulk Reject') }}
                        </button>
                        <button type="submit" class="btn btn-sm btn-outline-info" onclick="document.getElementById('bulkActionInput').value='publish'">
                            <i class="tio-send"></i> {{ translate('Bulk Publish') }}
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" style="width:40px;"></th>
                                    <th>{{ translate('Source') }}</th>
                                    <th>{{ translate('External ID') }}</th>
                                    <th>{{ translate('Origin') }}</th>
                                    <th>{{ translate('Destination') }}</th>
                                    <th>{{ translate('Pickup Date') }}</th>
                                    <th>{{ translate('Equipment') }}</th>
                                    <th>{{ translate('Rate') }}</th>
                                    <th>{{ translate('Mileage') }}</th>
                                    <th>{{ translate('Rate/Mile') }}</th>
                                    <th>{{ translate('Deadhead') }}</th>
                                    <th>{{ translate('Age') }}</th>
                                    <th>{{ translate('Duplicate') }}</th>
                                    <th>{{ translate('Validation') }}</th>
                                    <th>{{ translate('AI Score') }}</th>
                                    <th>{{ translate('Approval') }}</th>
                                    <th>{{ translate('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($externalLoads as $load)
                                <tr>
                                    <td class="text-center">
                                        <input class="form-check-input bulk-check" type="checkbox" name="load_ids[]" value="{{ $load->id }}">
                                    </td>
                                    <td><span class="badge badge-soft-info">{{ $load->source->name ?? translate('External') }}</span></td>
                                    <td><code>{{ $load->external_reference_id }}</code></td>
                                    <td>{{ $load->origin_city }}, {{ $load->origin_state }}</td>
                                    <td>{{ $load->destination_city }}, {{ $load->destination_state }}</td>
                                    <td><small>{{ $load->pickup_date ? \Carbon\Carbon::parse($load->pickup_date)->format('M d, Y') : translate('N/A') }}</small></td>
                                    <td><small>{{ ucwords(str_replace('_', ' ', $load->equipment_type ?? 'N/A')) }}</small></td>
                                    <td><strong class="text-success">${{ number_format($load->payout_amount ?? 0, 2) }}</strong></td>
                                    <td>{{ number_format($load->distance_miles ?? 0) }} mi</td>
                                    <td>${{ number_format($load->rate_per_mile ?? 0, 2) }}/mi</td>
                                    <td>{{ $load->deadhead_miles ?? '—' }} mi</td>
                                    <td><small class="text-muted">{{ $load->created_at ? $load->created_at->diffForHumans() : '—' }}</small></td>
                                    <td>
                                        @if($load->is_duplicate)
                                            <span class="badge badge-soft-warning">{{ translate('Duplicate') }}</span>
                                        @else
                                            <span class="badge badge-soft-success">{{ translate('Unique') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(($load->validation_status ?? '') === 'passed')
                                            <span class="badge badge-soft-success">{{ translate('Passed') }}</span>
                                        @elseif(($load->validation_status ?? '') === 'failed')
                                            <span class="badge badge-soft-danger">{{ translate('Failed') }}</span>
                                        @else
                                            <span class="badge badge-soft-secondary">{{ translate('Pending') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $score = $load->ai_score ?? 0;
                                        @endphp
                                        <span class="fw-bold {{ $score >= 75 ? 'text-success' : ($score >= 50 ? 'text-warning' : 'text-danger') }}">{{ $score }}</span>
                                    </td>
                                    <td>
                                        @php
                                            $approvalBadges = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger', 'draft' => 'secondary'];
                                            $approval = $load->approval_status ?? 'pending';
                                        @endphp
                                        <span class="badge badge-soft-{{ $approvalBadges[$approval] ?? 'secondary' }}">{{ ucfirst($approval) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            <a href="{{ route('admin.urban-goodz.load-sourcing.show-load', $load->id) }}" class="btn btn-sm btn-outline--primary" title="{{ translate('View') }}">
                                                <i class="tio-visible"></i>
                                            </a>
                                            @if(($load->approval_status ?? 'pending') === 'pending')
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.approve-load', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success" title="{{ translate('Approve') }}">
                                                    <i class="tio-checkmark-circle"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.reject-load', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Reject') }}">
                                                    <i class="tio-clear"></i>
                                                </button>
                                            </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.import-load', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ translate('Import') }}">
                                                    <i class="tio-download"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.publish-load', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info" title="{{ translate('Publish to Load Board') }}">
                                                    <i class="tio-send"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.assign-dispatcher', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="{{ translate('Assign to Dispatcher') }}">
                                                    <i class="tio-user"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.recommend-driver', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ translate('Recommend Driver') }}">
                                                    <i class="tio-star"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.archive-load', $load->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-dark" title="{{ translate('Archive') }}">
                                                    <i class="tio-archive"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="17" class="text-center text-muted py-4">
                                        {{ translate('No sourced loads found.') }}
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-end">
                    {{ $externalLoads->links() }}
                </div>
            </div>
        </form>

    </div>

    @push('script')
    <script>
        document.getElementById('selectAll')?.addEventListener('change', function() {
            document.querySelectorAll('.bulk-check').forEach(cb => cb.checked = this.checked);
        });
    </script>
    @endpush
@endsection
