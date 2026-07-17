@extends('layouts.admin.app')

@section('title', translate('Load Board'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Load Board') }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.load-board.create') }}" class="btn btn--primary">
                    <i class="tio-add"></i> {{ translate('Add Load') }}
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Available') }}</small>
                        <h3 class="mb-0 text-success">{{ $stats['total_available'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Sourced') }}</small>
                        <h3 class="mb-0 text-info">{{ $stats['total_sourced'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Under Review') }}</small>
                        <h3 class="mb-0 text-warning">{{ $stats['total_under_review'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Assigned') }}</small>
                        <h3 class="mb-0 text-info">{{ $stats['total_assigned'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('In Transit') }}</small>
                        <h3 class="mb-0 text-primary">{{ $stats['total_in_transit'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Delivered') }}</small>
                        <h3 class="mb-0 text-success">{{ $stats['total_delivered'] + $stats['total_completed'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('30d Revenue') }}</small>
                        <h3 class="mb-0 text-success">${{ number_format($stats['total_payout'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('30d Customer Charges') }}</small>
                        <h3 class="mb-0 text-primary">${{ number_format($stats['total_customer_charges'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('30d Platform Margin') }}</small>
                        <h3 class="mb-0 text-warning">${{ number_format($stats['total_platform_margin'], 2) }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Exceptions') }}</small>
                        <h3 class="mb-0 text-danger">{{ $stats['total_exception'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Search') }}</label>
                        <input type="text" name="search" class="form-control form-control-sm" value="{{ $filters['search'] ?? '' }}" placeholder="{{ translate('Load #, city, state...') }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Status') }}</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">{{ translate('All Active') }}</option>
                            @foreach($statuses as $status)
                            <option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Origin State') }}</label>
                        <input type="text" name="origin_state" class="form-control form-control-sm" value="{{ $filters['origin_state'] ?? '' }}" placeholder="TX">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Dest State') }}</label>
                        <input type="text" name="destination_state" class="form-control form-control-sm" value="{{ $filters['destination_state'] ?? '' }}" placeholder="CA">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label form-label-sm">{{ translate('Load Type') }}</label>
                        <select name="load_type" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            @foreach(['FTL', 'LTL', 'Partial', 'Last Mile', 'White Glove'] as $type)
                            <option value="{{ $type }}" {{ ($filters['load_type'] ?? '') === $type ? 'selected' : '' }}>{{ $type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label form-label-sm">{{ translate('Min Payout') }}</label>
                        <input type="number" name="min_payout" class="form-control form-control-sm" value="{{ $filters['min_payout'] ?? '' }}" step="50">
                    </div>
                    <div class="col-md-1 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn--primary flex-grow-1"><i class="tio-search"></i></button>
                        @if(count($filters) > 0)
                        <a href="{{ route('admin.urban-goodz.load-board.index') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Load #') }}</th>
                                <th>{{ translate('Origin') }}</th>
                                <th>{{ translate('Destination') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Miles') }}</th>
                                <th>{{ translate('Payout') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loads as $load)
                            <tr>
                                <td>{{ $load->id }}</td>
                                <td><strong>{{ $load->load_number ?? '-' }}</strong></td>
                                <td>
                                    <strong>{{ $load->origin_city ?? '-' }}</strong>, {{ $load->origin_state ?? '' }}
                                    @if($load->origin_zip)<br><small class="text-muted">{{ $load->origin_zip }}</small>@endif
                                </td>
                                <td>
                                    <strong>{{ $load->destination_city ?? '-' }}</strong>, {{ $load->destination_state ?? '' }}
                                    @if($load->destination_zip)<br><small class="text-muted">{{ $load->destination_zip }}</small>@endif
                                </td>
                                <td>
                                    @if($load->load_type)
                                    <span class="badge badge-soft-info">{{ $load->load_type }}</span>
                                    @endif
                                    @if($load->equipment_type)
                                    <span class="badge badge-soft-secondary">{{ $load->equipment_type }}</span>
                                    @endif
                                </td>
                                <td>{{ $load->distance_miles ? number_format($load->distance_miles, 0) : '-' }}</td>
                                <td><strong>${{ number_format($load->payout_amount, 2) }}</strong></td>
                                <td>
                                    <span class="badge {{ $load->status_badge_class }}">{{ $load->status_label }}</span>
                                </td>
                                <td><small>{{ $load->created_at->format('M d, Y') }}</small></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.urban-goodz.load-board.show', $load->id) }}" class="btn btn-sm btn-outline--primary" title="View">
                                            <i class="tio-visible"></i>
                                        </a>
                                        <a href="{{ route('admin.urban-goodz.load-board.edit', $load->id) }}" class="btn btn-sm btn-outline--primary" title="Edit">
                                            <i class="tio-pen"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">{{ translate('No loads found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $loads->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
