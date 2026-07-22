@extends('layouts.admin.app')

@section('title', translate('Load Sourcing — Sources'))

@section('content')
    <div class="content container-fluid">

        {{-- Sub-Navigation --}}
        <div class="card mb-3">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('admin.urban-goodz.load-sourcing.overview') }}" class="btn btn-outline--primary btn-sm">
                        <i class="tio-dashboard"></i> {{ translate('Overview') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.load-sourcing.sources') }}" class="btn btn--primary btn-sm">
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
                        <li class="breadcrumb-item active" aria-current="page">{{ translate('Sources') }}</li>
                    </ol>
                </nav>
                <h1 class="page-header-title">{{ translate('Load Sources & Connectors') }}</h1>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Source Name') }}</th>
                                <th class="text-center">{{ translate('Enabled') }}</th>
                                <th>{{ translate('Credential Status') }}</th>
                                <th>{{ translate('Last Sync') }}</th>
                                <th>{{ translate('Last Successful Sync') }}</th>
                                <th class="text-center">{{ translate('Records Imported') }}</th>
                                <th class="text-center">{{ translate('Errors') }}</th>
                                <th class="text-center">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $adapterNames = [
                                    'internal' => 'Internal',
                                    'manual' => 'Manual',
                                    'email' => 'Email',
                                    'dat' => 'DAT',
                                    'truckstop' => 'Truckstop',
                                    'trulos' => 'Trulos',
                                    'tb_load' => 'TB Load',
                                    'direct_freight' => 'Direct Freight',
                                    'truckerpath' => 'TruckerPath',
                                    'trucksmarter' => 'TruckSmarter',
                                ];
                            @endphp

                            @forelse($sources as $src)
                            <tr>
                                <td>
                                    <strong>{{ $adapterNames[$src->source_key] ?? $src->name }}</strong>
                                    <br><small class="text-muted">{{ $src->description ?? $src->source_key }}</small>
                                </td>
                                <td class="text-center">
                                    <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.toggle-source', $src->id) }}">
                                        @csrf
                                        @method('PUT')
                                        <label class="toggle-switch mb-0">
                                            <input type="hidden" name="enabled" value="0">
                                            <input type="checkbox" name="enabled" value="1" {{ $src->enabled ? 'checked' : '' }} onchange="this.form.submit()">
                                            <span class="toggle-switch-slider"></span>
                                        </label>
                                    </form>
                                </td>
                                <td>
                                    @if($src->credential_status === 'active')
                                        <span class="badge badge-soft-success"><i class="tio-checkmark-circle"></i> {{ translate('Active') }}</span>
                                    @elseif($src->credential_status === 'expired')
                                        <span class="badge badge-soft-danger"><i class="tio-warning"></i> {{ translate('Expired') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary"><i class="tio-clear"></i> {{ translate('Not Configured') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <small>{{ $src->last_sync_at ? $src->last_sync_at->diffForHumans() : translate('Never') }}</small>
                                </td>
                                <td>
                                    <small>{{ $src->last_successful_sync_at ? $src->last_successful_sync_at->diffForHumans() : translate('Never') }}</small>
                                </td>
                                <td class="text-center">
                                    <strong>{{ $src->records_imported_count ?? 0 }}</strong>
                                </td>
                                <td class="text-center">
                                    @if(($src->errors_count ?? 0) > 0)
                                        <span class="badge badge-soft-danger">{{ $src->errors_count }}</span>
                                    @else
                                        <span class="text-muted">0</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-1 justify-content-center flex-wrap">
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.test-connection', $src->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline--primary" title="{{ translate('Test Connection') }}">
                                                <i class="tio-plug"></i> {{ translate('Test') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.load-sourcing.sync-source', $src->id) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="{{ translate('Sync Now') }}">
                                                <i class="tio-refresh"></i> {{ translate('Sync') }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ translate('No load sources configured.') }}
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
@endsection
