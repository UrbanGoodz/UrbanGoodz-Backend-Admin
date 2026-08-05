@extends('layouts.admin.app')

@section('title', 'Mobile Release Management')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title"><i class="tio-android-vs-apple"></i> Mobile Release Management</h1>
                <p class="page-header-text">Automated In-App Update System & APK Release Manager for Shopper, Vendor, and Driver apps.</p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.mobile-releases.config') }}" class="btn btn-outline-primary mr-2"><i class="tio-settings"></i> Remote Config</a>
                <a href="{{ route('admin.mobile-releases.flags') }}" class="btn btn-outline-info mr-2"><i class="tio-toggle-on"></i> Feature Flags</a>
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadReleaseModal">
                    <i class="tio-upload-on-cloud"></i> Upload & Publish APK
                </button>
            </div>
        </div>
    </div>

    <!-- App Filter Tabs -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <ul class="nav nav-tabs border-0">
                <li class="nav-item">
                    <a class="nav-link {{ $app === 'shopper' ? 'active font-weight-bold' : '' }}" href="{{ route('admin.mobile-releases.index', ['app' => 'shopper']) }}">
                        <i class="tio-shopping-cart"></i> Customer (Shopper)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $app === 'vendor' ? 'active font-weight-bold' : '' }}" href="{{ route('admin.mobile-releases.index', ['app' => 'vendor']) }}">
                        <i class="tio-shop"></i> Vendor
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ $app === 'driver' ? 'active font-weight-bold' : '' }}" href="{{ route('admin.mobile-releases.index', ['app' => 'driver']) }}">
                        <i class="tio-car"></i> Driver
                    </a>
                </li>
            </ul>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <!-- Production Overview Cards -->
    <div class="row gx-2 gx-lg-3 mb-3">
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center">
                <h6 class="card-subtitle">Active Production Version</h6>
                <span class="card-title h2 text-primary">{{ $latest->version_name ?? 'N/A' }}</span>
                <span class="text-muted small">Build #{{ $latest->build_number ?? 0 }}</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center">
                <h6 class="card-subtitle">Minimum Supported Build</h6>
                <span class="card-title h2 text-warning">{{ $latest->minimum_build_number ?? 1 }}</span>
                <span class="text-muted small">Force Update Below This</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center">
                <h6 class="card-subtitle">Total Download Count</h6>
                <span class="card-title h2 text-success">{{ number_format($latest->download_count ?? 0) }}</span>
                <span class="text-muted small">APK Downloads</span>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-body text-center">
                <h6 class="card-subtitle">Verified Installs</h6>
                <span class="card-title h2 text-info">{{ number_format($latest->install_count ?? 0) }}</span>
                <span class="text-muted small">Crash Rate: {{ $latest && $latest->install_count > 0 ? number_format(($latest->crash_count / $latest->install_count) * 100, 2) : '0.00' }}%</span>
            </div>
        </div>
    </div>

    <!-- Active Rollback Action Bar -->
    <div class="card mb-3 bg-light border">
        <div class="card-body d-flex justify-content-between align-items-center py-2">
            <div>
                <strong>Rollback Release Protection:</strong> Reverts production {{ strtoupper($app) }} app to the prior stable APK immediately without recompilation.
            </div>
            <form action="{{ route('admin.mobile-releases.rollback') }}" method="POST" onsubmit="return confirm('Emergency Rollback: Are you sure you want to revert production {{ strtoupper($app) }} to the previous release?');">
                @csrf
                <input type="hidden" name="app_name" value="{{ $app }}">
                <input type="hidden" name="platform" value="{{ $platform }}">
                <button type="submit" class="btn btn-danger btn-sm"><i class="tio-history"></i> Rollback Release</button>
            </form>
        </div>
    </div>

    <!-- Releases Table -->
    <div class="card">
        <div class="card-header border-0">
            <h5 class="card-title"><i class="tio-format-list-bulleted"></i> Release History for {{ ucfirst($app) }} (Android)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Status</th>
                        <th>Version</th>
                        <th>Build #</th>
                        <th>Force Update</th>
                        <th>SHA256 Checksum</th>
                        <th>Release Date</th>
                        <th>Downloads</th>
                        <th>Installs</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($releases as $release)
                        <tr>
                            <td>
                                @if($release->enabled)
                                    <span class="badge badge-soft-success">ACTIVE PRODUCTION</span>
                                @else
                                    <span class="badge badge-soft-secondary">DISABLED</span>
                                @endif
                            </td>
                            <td>
                                <strong>v{{ $release->version_name }}</strong>
                                @if($release->rollback_version)
                                    <br><small class="text-danger">Rolled back from {{ $release->rollback_version }}</small>
                                @endif
                            </td>
                            <td>{{ $release->build_number }}</td>
                            <td>
                                @if($release->required)
                                    <span class="badge badge-danger">REQUIRED</span>
                                @else
                                    <span class="badge badge-soft-info">OPTIONAL</span>
                                @endif
                            </td>
                            <td>
                                <code class="small" title="{{ $release->sha256 }}">{{ Str::limit($release->sha256, 16) }}...</code>
                            </td>
                            <td>{{ $release->release_date ? $release->release_date->format('Y-m-d H:i') : $release->created_at->format('Y-m-d') }}</td>
                            <td>{{ number_format($release->download_count) }}</td>
                            <td>{{ number_format($release->install_count) }}</td>
                            <td class="text-center">
                                <a href="{{ route('admin.mobile-releases.toggle', $release->id) }}" class="btn btn-sm btn-outline-warning mr-1">
                                    {{ $release->enabled ? 'Disable' : 'Enable' }}
                                </a>
                                @if($release->apk_url)
                                    <a href="{{ $release->apk_url }}" target="_blank" class="btn btn-sm btn-outline-success mr-1" download>
                                        <i class="tio-download"></i> APK
                                    </a>
                                @endif
                                <a href="{{ route('admin.mobile-releases.destroy', $release->id) }}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this release record?');">
                                    <i class="tio-delete"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4">No release builds created yet for {{ ucfirst($app) }}. Upload an APK to begin.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload APK Modal -->
<div class="modal fade" id="uploadReleaseModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.mobile-releases.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="tio-upload-on-cloud"></i> Publish New Mobile Release</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Application <span class="text-danger">*</span></label>
                            <select name="app_name" class="form-control" required>
                                <option value="shopper" {{ $app === 'shopper' ? 'selected' : '' }}>Customer (Shopper App)</option>
                                <option value="vendor" {{ $app === 'vendor' ? 'selected' : '' }}>Vendor App</option>
                                <option value="driver" {{ $app === 'driver' ? 'selected' : '' }}>Driver App</option>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Platform <span class="text-danger">*</span></label>
                            <select name="platform" class="form-control" required>
                                <option value="android" selected>Android (APK)</option>
                                <option value="ios">iOS</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Version Name (e.g. 1.2.0) <span class="text-danger">*</span></label>
                            <input type="text" name="version_name" class="form-control" placeholder="1.2.0" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Build Number (e.g. 10200) <span class="text-danger">*</span></label>
                            <input type="number" name="build_number" class="form-control" placeholder="10200" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Minimum Supported Build Number</label>
                            <input type="number" name="minimum_build_number" class="form-control" placeholder="10000">
                            <small class="text-muted">Builds below this will be forced to update.</small>
                        </div>
                        <div class="col-md-6 form-group align-self-center">
                            <div class="custom-control custom-checkbox mt-3">
                                <input type="checkbox" name="required" value="1" class="custom-control-input" id="forceUpdateCheck">
                                <label class="custom-control-label font-weight-bold text-danger" for="forceUpdateCheck">Force Immediate Required Update</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Upload Production APK File</label>
                        <input type="file" name="apk_file" class="form-control-file" accept=".apk">
                        <small class="text-muted">SHA256 checksum and package signing fingerprint will be generated automatically.</small>
                    </div>
                    <div class="form-group">
                        <label>OR External Production APK Download URL</label>
                        <input type="url" name="apk_url" class="form-control" placeholder="https://storage.urbangoodz.app/releases/shopper.apk">
                    </div>
                    <div class="form-group">
                        <label>Release Notes</label>
                        <textarea name="release_notes" class="form-control" rows="3" placeholder="What's new in this release..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="tio-publish"></i> Publish Release</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
