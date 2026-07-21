@extends('business.layouts.app')
@section('title', translate('Dispatch Management'))
@section('content')
<div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="page-header-title">{{ translate('Dispatch Management') }}</h1>
        <p class="text-muted mb-0" style="color: #6c757d !important;">{{ translate('View and manage all dispatch requests') }}</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #6c757d;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Total Dispatches') }}</h6>
                <h3>{{ $stats['total'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #28a745;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Pending') }}</h6>
                <h3>{{ $stats['pending'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #17a2b8;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Sent') }}</h6>
                <h3>{{ $stats['sent'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #007bff;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Accepted') }}</h6>
                <h3>{{ $stats['accepted'] }}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6 col-12" style="flex: 1; min-width: 180px;">
        <div class="card h-100" style="border-left: 4px solid #dc3545;">
            <div class="card-body py-3">
                <h6 class="text-muted mb-1">{{ translate('Cancelled') }}</h6>
                <h3>{{ $stats['cancelled'] }}</h3>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('business.ai-logistics.dispatches.index') }}" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="{{ translate('Search by Dispatch ID, Driver, Load...') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-control">
                    <option value="">{{ translate('All Statuses') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>{{ translate('Sent') }}</option>
                    <option value="accepted" {{ request('status') === 'accepted' ? 'selected' : '' }}>{{ translate('Accepted') }}</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ translate('In Progress') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ translate('Completed') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn--primary flex-grow-1" style="background-color: var(--ug-primary); color: #fff;">
                    {{ translate('Filter') }}
                </button>
                <a href="{{ route('business.ai-logistics.dispatches.index') }}" class="btn btn-secondary">
                    {{ translate('Reset') }}
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-striped mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>{{ translate('Dispatch ID') }}</th>
                        <th>{{ translate('Driver') }}</th>
                        <th>{{ translate('Load Reference') }}</th>
                        <th class="text-center">{{ translate('Status') }}</th>
                        <th>{{ translate('Created At') }}</th>
                        <th class="text-center">{{ translate('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dispatches as $dispatch)
                    <tr>
                        <td>
                            <a href="{{ route('business.ai-logistics.dispatches.show', $dispatch->id) }}" class="fw-bold">
                                #{{ $dispatch->id }}
                            </a>
                        </td>
                        <td>{{ $dispatch->driver->f_name ?? '' }} {{ $dispatch->driver->l_name ?? '-' }}</td>
                        <td>
                            @if($dispatch->load)
                            <a href="{{ route('business.load-board.show', $dispatch->load->id) }}">
                                {{ $dispatch->load->load_number ?? '#'.$dispatch->load->id }}
                            </a>
                            @else
                            -
                            @endif
                        </td>
                        <td class="text-center">
                            @php($badgeClass = match($dispatch->status) { 'pending' => 'secondary', 'sent' => 'info', 'accepted' => 'success', 'in_progress' => 'primary', 'completed' => 'dark', 'cancelled' => 'danger', default => 'secondary' })
                            <span class="badge badge-soft-{{ $badgeClass }}">{{ ucfirst(str_replace('_', ' ', $dispatch->status)) }}</span>
                        </td>
                        <td>{{ $dispatch->created_at->format('Y-m-d H:i') }}</td>
                        <td class="text-center">
                            <div class="d-flex gap-1 justify-content-center">
                                <a href="{{ route('business.ai-logistics.dispatches.show', $dispatch->id) }}" class="btn btn-outline-info btn-xs p-1" title="{{ translate('View') }}">
                                    <i class="tio-visible"></i>
                                </a>
                                @if(!in_array($dispatch->status, ['cancelled', 'completed']))
                                <form action="{{ route('business.ai-logistics.dispatches.cancel', $dispatch->id) }}" method="POST" onsubmit="return confirm('{{ translate('Are you sure you want to cancel this dispatch?') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-danger btn-xs p-1" title="{{ translate('Cancel') }}">
                                        <i class="tio-clear"></i>
                                    </button>
                                </form>
                                @endif
                                @if(in_array($dispatch->status, ['pending', 'sent', 'cancelled']))
                                <form action="{{ route('business.ai-logistics.dispatches.resend', $dispatch->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-warning btn-xs p-1" title="{{ translate('Resend') }}">
                                        <i class="tio-refresh"></i>
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ translate('No dispatches found') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($dispatches->hasPages())
    <div class="card-footer py-2">
        {!! $dispatches->withQueryString()->links() !!}
    </div>
    @endif
</div>
@endsection