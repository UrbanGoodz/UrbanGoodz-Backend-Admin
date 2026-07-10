@extends('layouts.admin.app')

@section('title', $section['title'])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $section['title'] }}</h1>
            <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
        </div>

        @php
            $configData = collect(config('urban_goodz_admin_sections.capability_map', []))->firstWhere('key', $sectionKey ?? '');
            $tableName = $section['table'] ?? null;
            $dbCount = 0;
            $recentRecords = collect();
            $moduleRoute = $section['url'] ?? '#';

            if ($tableName && Schema::hasTable($tableName)) {
                try {
                    $modelClass = 'App\\Models\\' . Str::studly(Str::singular($tableName));
                    if (class_exists($modelClass)) {
                        $dbCount = $modelClass::count();
                        $recentRecords = $modelClass::latest()->take(5)->get();
                    }
                } catch (\Exception $e) {
                    $dbCount = 0;
                }
            }

            $statusBadgeMap = [
                'Live' => 'badge-soft-success',
                'Requires Configuration' => 'badge-soft-warning',
                'In Progress' => 'badge-soft-info',
                'Backend Required' => 'badge-soft-secondary',
                'Workflow Pending' => 'badge-soft-dark',
            ];
            $badgeClass = $statusBadgeMap[$section['status']] ?? 'badge-soft-info';
        @endphp

        <div class="row g-3 mb-3">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Status') }}</small>
                        <div><span class="badge {{ $badgeClass }}">{{ $section['status'] }}</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('DB Records') }}</small>
                        <div class="font-weight-bold h3 mb-0">{{ $dbCount }}</div>
                    </div>
                </div>
            </div>
            @if(!empty($section['revenue']))
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Revenue Enabled') }}</small>
                        <div><span class="badge badge-soft-success">{{ translate('Yes') }}</span></div>
                    </div>
                </div>
            </div>
            @endif
            @if(!empty($section['reportable']))
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <small class="text-muted">{{ translate('Reports') }}</small>
                        <div><span class="badge badge-soft-success">{{ translate('Available') }}</span></div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ translate('Workflow Summary') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6>{{ translate('Admin Workflow') }}</h6>
                        <p class="text-muted mb-0">{{ $section['admin_workflow'] ?? translate('No workflow defined') }}</p>
                        @if($configData && !empty($configData['description']))
                        <p class="text-muted mt-2 mb-0"><strong>{{ translate('Description') }}:</strong> {{ $configData['description'] }}</p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h6>{{ translate('Configuration') }}</h6>
                        <ul class="list-unstyled mb-0">
                            @if($tableName)
                            <li><strong>{{ translate('Database Table') }}:</strong> <code>{{ $tableName }}</code></li>
                            @endif
                            @if($configData && isset($configData['module_permission']))
                            <li><strong>{{ translate('Permission Key') }}:</strong> <code>{{ $configData['module_permission'] }}</code></li>
                            @endif
                            @if(!empty($section['customer_api']) && $section['customer_api'] !== '—')
                            <li><strong>{{ translate('Customer API') }}:</strong> {{ $section['customer_api'] }}</li>
                            @endif
                            <li><strong>{{ translate('Route') }}:</strong> <a href="{{ $moduleRoute }}">{{ $moduleRoute }}</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        @if($recentRecords->count() > 0)
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="card-title">{{ translate('Recent Records') }}</h5>
                <a href="{{ $moduleRoute }}" class="btn btn-sm btn--primary">{{ translate('View All') }}</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('ID') }}</th>
                            <th>{{ translate('Created') }}</th>
                            <th>{{ translate('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentRecords as $idx => $record)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>{{ $record->id }}</td>
                                <td>{{ $record->created_at ? $record->created_at->format('M d, Y H:i') : translate('N/A') }}</td>
                                <td>
                                    @php
                                        $status = $record->status ?? ($record->payment_status ?? '—');
                                    @endphp
                                    {{ $status }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @elseif($tableName && Schema::hasTable($tableName))
        <div class="card mt-3">
            <div class="card-body text-center py-5">
                <img src="{{ asset('assets/admin/img/empty.png') }}" alt="Empty" style="max-height: 120px;">
                <h5 class="mt-3">{{ translate('No records found') }}</h5>
                <p class="text-muted">{{ translate('This module has no data yet. Configure and add records to see them here.') }}</p>
            </div>
        </div>
        @endif

        @if(!empty($section['notes']))
        <div class="row mt-3">
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    <strong>{{ translate('Notes') }}:</strong> {{ $section['notes'] }}
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection