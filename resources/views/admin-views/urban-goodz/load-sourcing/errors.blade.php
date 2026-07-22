@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Errors'))

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
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sourced-loads') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-list-numbered"></i> {{ translate('Sourced Loads') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.recommendations') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-star"></i> {{ translate('Recommendations') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-refresh"></i> {{ translate('Sync Runs') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn--primary btn-sm">
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Errors') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Sync Errors') }}</h1>
            </div>
        </div>

        {{-- Filter Bar --}}
        <div class="card mb-3">
            <div class="card-body py-2">
                <form method="GET" action="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="d-flex flex-wrap gap-2 align-items-end">
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
                        <label class="form-label fw-bold mb-0" style="font-size:.75rem;">{{ translate('Resolved Status') }}</label>
                        <select name="resolved" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            <option value="0" {{ request('resolved') === '0' ? 'selected' : '' }}>{{ translate('Unresolved') }}</option>
                            <option value="1" {{ request('resolved') === '1' ? 'selected' : '' }}>{{ translate('Resolved') }}</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-sm btn--primary">
                            <i class="tio-filter"></i> {{ translate('Filter') }}
                        </button>
                        <a href="{{ route('admin.urban-goodz.load-sourcing.errors') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Clear') }}</a>
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
                                <th>{{ translate('Source') }}</th>
                                <th>{{ translate('Sync Run') }}</th>
                                <th>{{ translate('Error Code') }}</th>
                                <th>{{ translate('Error Message') }}</th>
                                <th>{{ translate('Context') }}</th>
                                <th>{{ translate('Created') }}</th>
                                <th>{{ translate('Resolved') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($errors_list as $error)
                            <tr>
                                <td><span class="badge badge-soft-info">{{ $error->source->name ?? translate('Unknown') }}</span></td>
                                <td>
                                    @if($error->sync_run)
                                        <a href="{{ route('admin.urban-goodz.load-sourcing.sync-runs') }}#run-{{ $error->sync_run_id }}">
                                            {{ translate('Run') }} #{{ $error->sync_run_id }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td><code>{{ $error->error_code }}</code></td>
                                <td>
                                    <small>{{ Str::limit($error->error_message, 120) }}</small>
                                    @if(strlen($error->error_message) > 120)
                                        <a href="#" class="ms-1" data-toggle="modal" data-target="#errorDetail{{ $error->id }}">
                                            <i class="tio-expand"></i>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <small class="text-muted">
                                        @if($error->context)
                                            @if(is_array($error->context))
                                                @foreach(array_slice($error->context, 0, 2) as $ctxKey => $ctxVal)
                                                    <strong>{{ $ctxKey }}:</strong> {{ Str::limit($ctxVal, 40) }}<br>
                                                @endforeach
                                            @else
                                                {{ Str::limit($error->context, 60) }}
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </small>
                                </td>
                                <td><small class="text-muted">{{ $error->created_at ? $error->created_at->diffForHumans() : '—' }}</small></td>
                                <td>
                                    @if($error->resolved_at)
                                        <span class="badge badge-soft-success">
                                            <i class="tio-checkmark-circle"></i> {{ translate('Resolved') }}
                                        </span>
                                    @else
                                        <span class="badge badge-soft-danger">
                                            <i class="tio-warning"></i> {{ translate('Unresolved') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center">
                                        @if(!$error->resolved_at)
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.resolve-error', $error->id) }}" class="d-inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ translate('Mark Resolved') }}">
                                                <i class="tio-checkmark-circle"></i> {{ translate('Resolve') }}
                                            </button>
                                        </form>
                                        @endif
                                        <a href="#" class="btn btn-sm btn-outline--primary" data-toggle="modal" data-target="#errorDetail{{ $error->id }}" title="{{ translate('View Details') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>

                            {{-- Detail Modal --}}
                            <div class="modal fade" id="errorDetail{{ $error->id }}" tabindex="-1" role="dialog">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">{{ translate('Error Details') }}</h5>
                                            <button type="button" class="close" data-dismiss="modal">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <dl class="row mb-0">
                                                <dt class="col-sm-3">{{ translate('Source') }}</dt>
                                                <dd class="col-sm-9">{{ $error->source->name ?? translate('Unknown') }}</dd>

                                                <dt class="col-sm-3">{{ translate('Error Code') }}</dt>
                                                <dd class="col-sm-9"><code>{{ $error->error_code }}</code></dd>

                                                <dt class="col-sm-3">{{ translate('Message') }}</dt>
                                                <dd class="col-sm-9">{{ $error->error_message }}</dd>

                                                @if($error->context)
                                                <dt class="col-sm-3">{{ translate('Context') }}</dt>
                                                <dd class="col-sm-9">
                                                    <pre class="bg-light p-2 rounded mb-0" style="font-size:.8rem;">{{ is_array($error->context) ? json_encode($error->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $error->context }}</pre>
                                                </dd>
                                                @endif

                                                <dt class="col-sm-3">{{ translate('Created') }}</dt>
                                                <dd class="col-sm-9">{{ $error->created_at ? $error->created_at->format('M d, Y H:i:s') : '—' }}</dd>
                                            </dl>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ translate('No errors recorded. Everything looks good!') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if(isset($errors_list) && $errors_list instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="card-footer d-flex justify-content-end">
                {{ $errors_list->links() }}
            </div>
            @endif
        </div>

    </div>
@endsection
