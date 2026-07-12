@extends('layouts.admin.app')

@section('title', translate('Service Provider Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ $provider->business_name }}</h1>
                    <p class="text-muted mb-0" style="color: #6c757d !important;">{{ $provider->contact_name }} &middot; {{ $provider->email }}</p>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.service-providers.edit', $provider->id) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('Edit') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.service-providers.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            <span class="badge badge-soft-{{ $provider->is_verified ? 'success' : 'secondary' }}">
                                {{ $provider->is_verified ? translate('Verified') : translate('Unverified') }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Verification') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            <span class="badge badge-soft-{{ $provider->is_active ? 'success' : 'danger' }}">
                                {{ $provider->is_active ? translate('Active') : translate('Inactive') }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $provider->service_category ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Category') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $provider->phone ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Phone') }}</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Details') }}</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>{{ translate('Slug') }}:</strong> <code>{{ $provider->slug }}</code></p>
                        <p><strong>{{ translate('Email') }}:</strong> {{ $provider->email ?? '-' }}</p>
                        <p><strong>{{ translate('Phone') }}:</strong> {{ $provider->phone ?? '-' }}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Description') }}:</strong></p>
                        <p>{{ $provider->description ?? '-' }}</p>
                        <p><strong>{{ translate('Service Areas') }}:</strong></p>
                        @if(is_array($provider->service_areas) && count($provider->service_areas))
                            <div>
                                @foreach($provider->service_areas as $area)
                                    <span class="badge badge-soft-info">{{ $area }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-muted">-</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
