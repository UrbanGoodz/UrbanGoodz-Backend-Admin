@extends('delivery-man-views.urban-goodz.layout')

@section('title', 'Urban Goodz Driver')

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">Urban Goodz Driver / Courier Panel</h1>
    </div>
    <div class="row g-3">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>Assigned Order Anywhere Jobs</h5>
                    <h3>{{ $assignedCount }}</h3>
                    <a href="{{ route('delivery-man.urban-goodz.order-anywhere.index') }}" class="btn btn--primary">Open jobs</a>
                </div>
            </div>
        </div>
        @foreach(['logistics','load-board','medical-courier','custody','earn-money'] as $section)
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5>{{ str($section)->replace('-', ' ')->title() }}</h5>
                        <p class="text-muted">Driver route reserved. Real job table is still required.</p>
                        <a href="{{ route('delivery-man.urban-goodz.section', $section) }}" class="btn btn--secondary">View status</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
