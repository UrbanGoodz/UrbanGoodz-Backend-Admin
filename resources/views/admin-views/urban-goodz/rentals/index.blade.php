@extends('layouts.admin.app')

@section('title', $section['title'])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ $section['title'] }}</h1>
                    <p class="text-muted mb-0">{{ $section['notes'] }}</p>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        @if(addon_published_status('Rental'))
            <div class="alert alert-info">
                <i class="tio-info-outined mr-2"></i>
                {{ translate('The Urban Goodz Rental module is active. Use the existing admin/rental/* routes for trip, provider, and vehicle management.') }}
                <div class="mt-2">
                    <a href="{{ route('admin.urban-goodz.rentals.dashboard') }}" class="btn btn-sm btn--primary">{{ translate('Rental Dashboard') }}</a>
                </div>
            </div>
        @endif

        <div class="row g-3">
            @foreach($businessTypes as $bt)
                <div class="col-md-6 col-xl-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">
                                {{ $bt->name }}
                                <code class="ml-1 small text-muted">{{ $bt->slug }}</code>
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small mb-2">{{ $bt->description }}</p>
                            <label class="font-weight-bold small">{{ translate('Capabilities') }} ({{ $bt->capabilities->count() }})</label>
                            <ul class="list-unstyled mb-0">
                                @foreach($bt->capabilities as $cap)
                                    <li class="d-flex align-items-center mb-1">
                                        <span class="badge badge-soft-info mr-2">{{ $cap->slug }}</span>
                                        <small>{{ $cap->name }}</small>
                                        @if($cap->pivot?->is_required)
                                            <span class="badge badge-soft-danger ml-auto">required</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card mt-3">
            <div class="card-header">
                <h5>{{ translate('Integration Status') }}</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <p><span class="badge badge-soft-info">{{ $section['status'] }}</span></p>
                        <p class="text-muted">{{ $section['admin_workflow'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled mb-0">
                            <li><strong>{{ translate('Database') }}:</strong> {{ $section['table'] }}</li>
                            <li><strong>{{ translate('Customer API') }}:</strong> {{ $section['customer_api'] }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
