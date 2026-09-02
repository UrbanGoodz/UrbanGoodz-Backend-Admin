@extends('layouts.admin.app')

@section('title', translate('Historical Reconstruction'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn-outline--primary">
                    <i class="tio-arrow-backward"></i> {{ translate('Back to Control Center') }}
                </a>
            </div>
            <h1 class="page-header-title">{{ translate('Historical Operations Reconstruction') }}</h1>
        </div>

        <div class="alert alert-warning mb-3">
            <strong>{{ translate('Evidentiary Disclosure') }}:</strong>
            {{ translate('The original production database was lost during a subsequent application rebuild. This report reconstructs historical business operations using surviving business records and owner-provided historical operating assumptions. Reconstructed values are estimates and are not represented as recovered original database records.') }}
        </div>

        <div class="d-flex justify-content-end mb-3">
            <a href="{{ route('admin.urban-goodz.historical-reconstruction.create') }}" class="btn btn--primary">
                <i class="tio-add"></i> {{ translate('New Reconstruction') }}
            </a>
        </div>

        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">{{ translate('Reconstruction Configurations') }}</h5>
                <form method="GET" class="d-flex gap-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ translate('Search configurations...') }}" value="{{ request('search') }}">
                    <button type="submit" class="btn btn-sm btn--primary"><i class="tio-search"></i></button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Configuration') }}</th>
                                <th>{{ translate('Date Range') }}</th>
                                <th>{{ translate('Months') }}</th>
                                <th>{{ translate('Snapshots') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($configs as $config)
                            <tr>
                                <td>{{ $config->id }}</td>
                                <td><strong>{{ $config->configuration_name }}</strong></td>
                                <td>
                                    <small>{{ $config->reconstruction_start_date->format('M Y') }} - {{ $config->reconstruction_end_date->format('M Y') }}</small>
                                </td>
                                <td>{{ $config->month_count }}</td>
                                <td>{{ $config->snapshots_count }}</td>
                                <td>
                                    @if($config->is_published)
                                        <span class="badge badge-soft-success">{{ translate('Published') }}</span>
                                    @elseif($config->snapshots_count > 0)
                                        <span class="badge badge-soft-info">{{ translate('Draft') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('Not Generated') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('admin.urban-goodz.historical-reconstruction.show', $config->id) }}" class="btn btn-sm btn-outline--primary" title="{{ translate('View') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                        <a href="{{ route('admin.urban-goodz.historical-reconstruction.edit', $config->id) }}" class="btn btn-sm btn-outline--secondary" title="{{ translate('Edit') }}">
                                            <i class="tio-edit"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.urban-goodz.historical-reconstruction.run', $config->id) }}" class="d-inline" onsubmit="return confirm('{{ translate('Run reconstruction? This will regenerate all monthly snapshots.') }}')">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline--warning" title="{{ translate('Run Reconstruction') }}">
                                                <i class="tio-play"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No reconstruction configurations found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $configs->links() }}
            </div>
        </div>
    </div>
@endsection
