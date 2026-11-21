@extends('layouts.vendor.app')

@section('title', 'My Drivers Map')

@section('content')
<div class="content container-fluid">
    <div class="page-header pb-2 mb-3">
        <h1 class="page-header-title"><i class="tio-map nav-icon mr-2"></i> My Drivers Map</h1>
        <p class="text-muted mb-0">Where your own drivers are right now. Updates every 15 seconds.</p>
    </div>
    @include('partials.fleet-map', ['dataUrl' => route('vendor.fleet-map.data'), 'mapKey' => $mapKey])
</div>
@endsection
