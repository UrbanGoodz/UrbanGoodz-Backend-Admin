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
        @endphp

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h5>{{ translate('Availability') }}</h5>
                        @if($configData && isset($configData['availability_status']))
                            @switch($configData['availability_status'])
                                @case('active')
                                    <p><span class="badge badge-soft-success">Active — Full workflow connected</span></p>
                                    @break
                                @case('backend_controlled')
                                    <p><span class="badge badge-soft-warning">Backend-Controlled — Enable via admin role permissions</span></p>
                                    @break
                                @case('admin_only')
                                    <p><span class="badge badge-soft-info">Admin Only — Configuration and setup</span></p>
                                    @break
                                @case('disabled_until_configured')
                                    <p><span class="badge badge-soft-secondary">Disabled — Requires backend configuration</span></p>
                                    @break
                                @default
                                    <p><span class="badge badge-soft-info">{{ $section['status'] }}</span></p>
                            @endswitch
                            <p class="text-muted mb-0">
                                <strong>{{ translate('Permission Key') }}:</strong> {{ $configData['module_permission'] ?? '—' }}
                            </p>
                            <p class="text-muted mb-0">
                                <strong>{{ translate('Route') }}:</strong> {{ $configData['route'] ?? '—' }}
                            </p>
                        @else
                            <p><span class="badge badge-soft-info">{{ $section['status'] }}</span></p>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <h5>{{ translate('Section Information') }}</h5>
                        <ul class="list-unstyled mb-0">
                            @if(!empty($section['table']) && $section['table'] !== '—')
                            <li><strong>{{ translate('Database') }}:</strong> {{ $section['table'] }}</li>
                            @endif
                            @if(!empty($section['customer_api']) && $section['customer_api'] !== '—')
                            <li><strong>{{ translate('Customer API') }}:</strong> {{ $section['customer_api'] }}</li>
                            @endif
                            @if(!empty($section['admin_workflow']))
                            <li><strong>{{ translate('Admin workflow') }}:</strong> {{ $section['admin_workflow'] }}</li>
                            @endif
                            @if(!empty($configData['description']))
                            <li><strong>{{ translate('Description') }}:</strong> {{ $configData['description'] }}</li>
                            @endif
                        </ul>
                    </div>
                </div>
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
        </div>
    </div>
@endsection
