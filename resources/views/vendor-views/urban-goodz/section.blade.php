@extends('layouts.vendor.app')

@section('title', $title)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $title }}</h1>
            <a href="{{ route('vendor.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-muted mb-0">{{ translate('This vendor/provider route is reserved for scoped Urban Goodz records. No vendor-owned database workflow exists for this feature yet.') }}</p>
            </div>
        </div>
    </div>
@endsection
