@extends('layouts.admin.app')

@section('title', translate('Activity Logs'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back to Control Center') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Activity Logs') }}</h1>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Total Entries') }}</div>
                    <div class="h3">{{ $logs->total() }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Unique Events') }}</div>
                    <div class="h3">{{ $events->count() }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Causer Types') }}</div>
                    <div class="h3">{{ $causerTypes->count() }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-3">
                    <div class="text-muted">{{ translate('Loggable Types') }}</div>
                    <div class="h3">{{ $loggableTypes->count() }}</div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('All Activity Logs') }}</h5>
                <form method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ translate('Search description or event...') }}" value="{{ request('search') }}">

                    <select name="event" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Events') }}</option>
                        @foreach($events as $e)
                        <option value="{{ $e }}" {{ request('event') === $e ? 'selected' : '' }}>{{ $e }}</option>
                        @endforeach
                    </select>

                    <select name="causer_type" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Causer Types') }}</option>
                        @foreach($causerTypes as $ct)
                        <option value="{{ $ct }}" {{ request('causer_type') === $ct ? 'selected' : '' }}>{{ class_basename($ct) }}</option>
                        @endforeach
                    </select>

                    <select name="loggable_type" class="form-control form-control-sm" onchange="this.form.submit()">
                        <option value="">{{ translate('All Loggable Types') }}</option>
                        @foreach($loggableTypes as $lt)
                        <option value="{{ $lt }}" {{ request('loggable_type') === $lt ? 'selected' : '' }}>{{ class_basename($lt) }}</option>
                        @endforeach
                    </select>

                    <input type="date" name="from_date" class="form-control form-control-sm" value="{{ request('from_date') }}" title="{{ translate('From date') }}">
                    <input type="date" name="to_date" class="form-control form-control-sm" value="{{ request('to_date') }}" title="{{ translate('To date') }}">

                    <button type="submit" class="btn btn-sm btn--primary"><i class="tio-search"></i></button>
                    @if(count(request()->query()) > 0)
                    <a href="{{ route('admin.urban-goodz.activity-logs.index') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                    @endif
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Timestamp') }}</th>
                                <th>{{ translate('Event') }}</th>
                                <th>{{ translate('Description') }}</th>
                                <th>{{ translate('Causer') }}</th>
                                <th>{{ translate('Loggable') }}</th>
                                <th>{{ translate('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>{{ $log->id }}</td>
                                <td><small>{{ $log->created_at->format('M d, Y h:i A') }}</small></td>
                                <td>
                                    @if($log->event)
                                    <span class="badge badge-soft-info">{{ $log->event }}</span>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <span style="max-width:250px; display:inline-block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        {{ $log->description ?? '-' }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->causer_type)
                                    <small>
                                        {{ class_basename($log->causer_type) }}
                                        <span class="text-muted">#{{ $log->causer_id }}</span>
                                    </small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    @if($log->loggable_type)
                                    <small>
                                        {{ class_basename($log->loggable_type) }}
                                        <span class="text-muted">#{{ $log->loggable_id }}</span>
                                    </small>
                                    @else
                                    <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.activity-logs.show', $log->id) }}" class="btn btn-sm btn-outline--primary">
                                        <i class="tio-visible"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No activity logs found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
@endsection
