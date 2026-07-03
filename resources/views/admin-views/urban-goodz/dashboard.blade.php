@extends('layouts.admin.app')

@section('title', translate('Urban Goodz Admin Control Center'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Urban Goodz Admin Control Center') }}</h1>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Order Anywhere') }}</h6>
                        <h3>{{ $counts['order_anywhere'] ?? 0 }}</h3>
                        <span class="badge badge-soft-success">{{ translate('DB-backed') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6>{{ translate('Fashion Fit') }}</h6>
                        <h3>{{ $counts['fashion_fit'] ?? 0 }}</h3>
                        <span class="badge badge-soft-warning">{{ translate('Partial') }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            @foreach($sections as $key => $section)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0">{{ $section['title'] }}</h5>
                                <span class="badge {{ $section['status'] === 'DB-backed' ? 'badge-soft-success' : 'badge-soft-info' }}">
                                    {{ $section['status'] }}
                                </span>
                            </div>
                            <p class="text-muted mb-2">{{ $section['admin_workflow'] }}</p>
                            <div class="small text-muted mb-3">
                                <div><strong>{{ translate('Table') }}:</strong> {{ $section['table'] }}</div>
                                <div><strong>{{ translate('API') }}:</strong> {{ $section['customer_api'] }}</div>
                            </div>
                            <a href="{{ $section['url'] }}" class="btn btn--primary mt-auto">{{ translate('Open') }}</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
