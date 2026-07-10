@extends('layouts.admin.app')

@section('title', translate('Route Packages') . ' - ' . $route->route_name)

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Packages') }}: {{ $route->route_name }}</h1>
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#addPackageModal">
                    <i class="tio-add"></i> {{ translate('Add Package') }}
                </button>
                <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#csvUploadModal">
                    <i class="tio-file"></i> {{ translate('Upload CSV') }}
                </button>
                <a href="{{ route('admin.urban-goodz.dedicated-routes.show', $route->id) }}" class="btn btn-secondary">
                    <i class="tio-back"></i> {{ translate('Back') }}
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h5>{{ translate('Packages') }} ({{ $route->packages->count() }})</h5>
                <div>
                    <span class="badge badge-soft-primary">{{ $route->packages->where('status', 'pending')->count() }} pending</span>
                    <span class="badge badge-soft-info">{{ $route->packages->whereIn('status', ['picked_up', 'in_transit'])->count() }} in transit</span>
                    <span class="badge badge-soft-success">{{ $route->packages->where('status', 'delivered')->count() }} delivered</span>
                    <span class="badge badge-soft-danger">{{ $route->packages->where('status', 'failed')->count() }} failed</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-borderless table-nowrap">
                        <thead class="thead-light">
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Tracking ID') }}</th>
                                <th>{{ translate('Dropoff') }}</th>
                                <th>{{ translate('Phone') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Priority') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Pickup Scan') }}</th>
                                <th>{{ translate('Dropoff Scan') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($route->packages as $key => $pkg)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>
                                        <a href="{{ route('admin.urban-goodz.dedicated-routes.package-show', [$route->id, $pkg->id]) }}" class="text-primary">
                                            {{ $pkg->tracking_id }}
                                        </a>
                                        @if($pkg->external_reference)
                                            <br><small class="text-muted">Ref: {{ $pkg->external_reference }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        {{ $pkg->dropoff_name ?? 'N/A' }}<br>
                                        <small class="text-muted">{{ Str::limit($pkg->dropoff_address, 40) }}</small>
                                    </td>
                                    <td>{{ $pkg->dropoff_phone ?? '—' }}</td>
                                    <td>{{ ucfirst($pkg->package_type) }}</td>
                                    <td>
                                        @php $pMap = ['normal' => 'secondary', 'high' => 'info', 'urgent' => 'warning', 'medical' => 'danger']; @endphp
                                        <span class="badge badge-soft-{{ $pMap[$pkg->priority] ?? 'secondary' }}">{{ ucfirst($pkg->priority) }}</span>
                                    </td>
                                    <td>
                                        @php $sMap = ['pending' => 'secondary', 'picked_up' => 'info', 'in_transit' => 'warning', 'delivered' => 'success', 'failed' => 'danger', 'returned' => 'dark']; @endphp
                                        <span class="badge badge-soft-{{ $sMap[$pkg->status] ?? 'secondary' }}">{{ ucwords(str_replace('_', ' ', $pkg->status)) }}</span>
                                    </td>
                                    <td>
                                        @if($pkg->pickup_scanned_at)
                                            {{ $pkg->pickup_scanned_at->format('M d, g:i A') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($pkg->dropoff_scanned_at)
                                            {{ $pkg->dropoff_scanned_at->format('M d, g:i A') }}
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('admin.urban-goodz.dedicated-routes.package-show', [$route->id, $pkg->id]) }}" class="btn btn-sm btn-outline-info" title="{{ translate('View') }}">
                                                <i class="tio-visible"></i>
                                            </a>
                                            <a href="{{ route('admin.urban-goodz.dedicated-routes.package-scans', [$route->id, $pkg->id]) }}" class="btn btn-sm btn-outline-secondary" title="{{ translate('Scans') }}">
                                                <i class="tio-history"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center py-4">{{ translate('No packages on this route') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Package Modal -->
    <div class="modal fade" id="addPackageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="{{ route('admin.urban-goodz.dedicated-routes.package-store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="dedicated_route_id" value="{{ $route->id }}">
                <input type="hidden" name="business_client_id" value="{{ $route->business_client_id }}">
                <div class="modal-header"><h5>{{ translate('Add Package') }}</h5><button type="button" class="close" data-dismiss="modal">×</button></div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Tracking ID') }}</label>
                            <input type="text" name="tracking_id" class="form-control" placeholder="Auto-generated if blank">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('External Reference') }}</label>
                            <input type="text" name="external_reference" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Dropoff Name') }}</label>
                            <input type="text" name="dropoff_name" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">{{ translate('Dropoff Phone') }}</label>
                            <input type="text" name="dropoff_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Dropoff Address') }} <span class="text-danger">*</span></label>
                            <input type="text" name="dropoff_address" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Package Type') }}</label>
                            <select name="package_type" class="form-control">
                                <option value="parcel">Parcel</option>
                                <option value="document">Document</option>
                                <option value="specimen">Specimen</option>
                                <option value="supply">Supply</option>
                                <option value="pallet">Pallet</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Weight') }}</label>
                            <input type="number" name="weight" class="form-control" step="0.01">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">{{ translate('Priority') }}</label>
                            <select name="priority" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                                <option value="medical">Medical</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="requires_signature" class="form-check-input" id="reqSig" value="1">
                                <label class="form-check-label" for="reqSig">{{ translate('Signature Required') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="requires_photo" class="form-check-input" id="reqPhoto" value="1">
                                <label class="form-check-label" for="reqPhoto">{{ translate('Photo Required') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-check mt-4">
                                <input type="checkbox" name="requires_custody" class="form-check-input" id="reqCustody" value="1">
                                <label class="form-check-label" for="reqCustody">{{ translate('Custody Required') }}</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">{{ translate('Temperature') }}</label>
                            <select name="temperature_requirement" class="form-control">
                                <option value="">{{ translate('Ambient') }}</option>
                                <option value="refrigerated">Refrigerated</option>
                                <option value="frozen">Frozen</option>
                                <option value="controlled">Controlled</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">{{ translate('Notes') }}</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Add Package') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- CSV Upload Modal -->
    <div class="modal fade" id="csvUploadModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('admin.urban-goodz.dedicated-routes.package-bulk-store') }}" method="POST" class="modal-content">
                @csrf
                <input type="hidden" name="dedicated_route_id" value="{{ $route->id }}">
                <input type="hidden" name="business_client_id" value="{{ $route->business_client_id }}">
                <div class="modal-header"><h5>{{ translate('Upload CSV') }}</h5><button type="button" class="close" data-dismiss="modal">×</button></div>
                <div class="modal-body">
                    <p>{{ translate('Paste CSV data with headers: tracking_id, dropoff_name, dropoff_address, dropoff_phone, package_type, weight, priority, external_reference, notes') }}</p>
                    <textarea name="packages_csv" class="form-control" rows="10" placeholder="tracking_id,dropoff_name,dropoff_address,dropoff_phone,package_type,weight,priority,external_reference,notes&#10;PKG001,John Doe,123 Main St NY,555-0100,parcel,2.5,normal,REF001,Handle with care"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ translate('Import CSV') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection
