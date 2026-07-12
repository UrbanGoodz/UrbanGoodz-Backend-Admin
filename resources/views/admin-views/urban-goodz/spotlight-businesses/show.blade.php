@extends('layouts.admin.app')

@section('title', translate('Spotlight Business Details'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">{{ $business->business_name }}</h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.spotlight-businesses.edit', $business->id) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('Edit') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.spotlight-businesses.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            <span class="badge badge-soft-{{ $business->is_active ? 'success' : 'danger' }}">
                                {{ $business->is_active ? translate('Active') : translate('Inactive') }}
                            </span>
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Status') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h5 class="mb-0">
                            @if($business->is_featured)
                                <span class="badge badge-soft-warning"><i class="tio-star"></i> {{ translate('Featured') }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </h5>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Featured') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $business->category ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Category') }}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card">
                    <div class="card-body text-center py-3">
                        <h6 class="mb-0">{{ $business->featured_until?->format('M d, Y') ?? '-' }}</h6>
                        <small class="text-muted" style="color: #6c757d !important;">{{ translate('Featured Until') }}</small>
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
                        <p><strong>{{ translate('Business Name') }}:</strong> {{ $business->business_name }}</p>
                        <p><strong>{{ translate('Vendor ID') }}:</strong> {{ $business->vendor_id ?? '-' }}</p>
                        <p><strong>{{ translate('Image URL') }}:</strong>
                            @if($business->image_url)
                                <a href="{{ $business->image_url }}" target="_blank">{{ translate('View Image') }}</a>
                            @else
                                -
                            @endif
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>{{ translate('Description') }}:</strong></p>
                        <p>{{ $business->description ?? '-' }}</p>
                    </div>
                </div>
            </div>
        </div>

        @if($business->image_url)
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ translate('Preview') }}</h5>
            </div>
            <div class="card-body text-center">
                <img src="{{ $business->image_url }}" alt="{{ $business->business_name }}" class="img-fluid" style="max-height: 300px;">
            </div>
        </div>
        @endif
    </div>
@endsection
