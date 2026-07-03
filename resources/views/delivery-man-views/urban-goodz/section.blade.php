@extends('delivery-man-views.urban-goodz.layout')

@section('title', $title)

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">{{ $title }}</h1>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-0">This driver/courier route is reserved for assigned or eligible Urban Goodz jobs. The feature-specific database workflow is not present yet.</p>
        </div>
    </div>
@endsection
