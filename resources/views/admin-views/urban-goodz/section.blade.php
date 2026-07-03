@extends('layouts.admin.app')

@section('title', $section['title'])

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $section['title'] }}</h1>
            <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h5>{{ translate('Integration status') }}</h5>
                        <p><span class="badge badge-soft-info">{{ $section['status'] }}</span></p>
                        <p class="text-muted">{{ $section['notes'] }}</p>
                    </div>
                    <div class="col-md-6">
                        <h5>{{ translate('Route and data map') }}</h5>
                        <ul class="list-unstyled mb-0">
                            <li><strong>{{ translate('Customer API') }}:</strong> {{ $section['customer_api'] }}</li>
                            <li><strong>{{ translate('Database') }}:</strong> {{ $section['table'] }}</li>
                            <li><strong>{{ translate('Admin workflow') }}:</strong> {{ $section['admin_workflow'] }}</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
