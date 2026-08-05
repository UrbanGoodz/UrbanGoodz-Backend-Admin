@extends('layouts.admin.app')

@section('title', 'Remote Config Management')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title"><i class="tio-settings"></i> Remote Config Engine</h1>
                <p class="page-header-text">Dynamic runtime configuration for Fashion Fit, Marketplace Modules, and UI constraints without re-deploying APKs.</p>
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
            <h5 class="card-title">Remote Configuration Parameters</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Key</th>
                        <th>Target App / Platform</th>
                        <th>Value (JSON / Value)</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($configs as $cfg)
                        <tr>
                            <td><strong>{{ $cfg->key }}</strong></td>
                            <td><span class="badge badge-soft-info">{{ $cfg->app_name }} / {{ $cfg->platform }}</span></td>
                            <td>
                                <form action="{{ route('admin.mobile-releases.config-update', $cfg->id) }}" method="POST" id="config-form-{{ $cfg->id }}">
                                    @csrf
                                    <textarea name="value" class="form-control font-monospace small" rows="4">{{ is_array($cfg->value) ? json_encode($cfg->value, JSON_PRETTY_PRINT) : $cfg->value }}</textarea>
                            </td>
                            <td><small class="text-muted">{{ $cfg->description }}</small></td>
                            <td>
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="switch-{{ $cfg->id }}" {{ $cfg->is_active ? 'checked' : '' }}>
                                    <label class="custom-control-label" for="switch-{{ $cfg->id }}">{{ $cfg->is_active ? 'Active' : 'Inactive' }}</label>
                                </div>
                            </td>
                            <td>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="tio-save"></i> Save</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
