@extends('layouts.admin.app')

@section('title', 'Feature Flags Management')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title"><i class="tio-toggle-on"></i> Feature Flags Engine</h1>
                <p class="page-header-text">Runtime feature toggles for Fashion Fit, Virtual Try-On, Stylist Requests, Creator Commerce, Events, Rentals, Order Anywhere, Driver Scanner, and Experimental Features.</p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.mobile-releases.index') }}" class="btn btn-outline-secondary"><i class="tio-back-ui"></i> Mobile Releases</a>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header border-0">
            <h5 class="card-title">Runtime Feature Toggles</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Feature Name</th>
                        <th>Flag Key</th>
                        <th>Description</th>
                        <th>Global State</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($flags as $flag)
                        <tr>
                            <td><strong>{{ $flag->name }}</strong></td>
                            <td><code>{{ $flag->key }}</code></td>
                            <td><small class="text-muted">{{ $flag->description }}</small></td>
                            <td>
                                @if($flag->enabled_globally)
                                    <span class="badge badge-soft-success">ENABLED</span>
                                @else
                                    <span class="badge badge-soft-danger">DISABLED</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.mobile-releases.flag-toggle', $flag->id) }}" class="btn btn-sm {{ $flag->enabled_globally ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                    {{ $flag->enabled_globally ? 'Disable Feature' : 'Enable Feature' }}
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
