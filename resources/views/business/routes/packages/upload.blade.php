@extends('business.layouts.app')

@section('title', translate('Upload Packages CSV'))

@section('content')
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
        <h1 class="page-header-title">{{ translate('Upload CSV') }}: {{ $route->route_name }}</h1>
        <a href="{{ route('business.routes.packages', $route->id) }}" class="btn btn-secondary">
            {{ translate('Back to Packages') }}
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{ route('business.routes.packages.bulk-store', $route->id) }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="input-label">{{ translate('CSV Data') }} <span class="text-danger">*</span></label>
                    <p class="text-muted small" style="color: #6c757d !important;">
                        {{ translate('Paste CSV data with headers. Required: dropoff_address. Optional: dropoff_name, dropoff_city, dropoff_state, dropoff_zip, dropoff_phone, package_type, weight, priority, notes.') }}
                    </p>
                    <textarea name="packages_csv" class="form-control" rows="12" placeholder="dropoff_name,dropoff_address,dropoff_city,dropoff_state,dropoff_zip,dropoff_phone,package_type,weight,priority,notes&#10;John Doe,123 Main St,Houston,TX,77001,555-0100,parcel,2.5,normal,Leave at front door&#10;Jane Smith,456 Oak Ave,Austin,TX,73301,555-0200,parcel,1.0,high,Call on arrival">{{ old('packages_csv') }}</textarea>
                </div>

                <div class="alert alert-info" role="info">
                    <i class="tio-info"></i>
                    {{ translate('Each row creates one package/drop-off stop. All packages will be added with status "pending". The route total_packages will be updated automatically.') }}
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('business.routes.packages', $route->id) }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
                    <button type="submit" class="btn btn--primary">{{ translate('Import Packages') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header"><h5>{{ translate('CSV Format Example') }}</h5></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>dropoff_name</th>
                            <th>dropoff_address</th>
                            <th>dropoff_city</th>
                            <th>dropoff_state</th>
                            <th>dropoff_zip</th>
                            <th>dropoff_phone</th>
                            <th>package_type</th>
                            <th>weight</th>
                            <th>priority</th>
                            <th>notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>John Doe</td>
                            <td>123 Main St</td>
                            <td>Houston</td>
                            <td>TX</td>
                            <td>77001</td>
                            <td>555-0100</td>
                            <td>parcel</td>
                            <td>2.5</td>
                            <td>normal</td>
                            <td>Leave at front door</td>
                        </tr>
                        <tr>
                            <td>Jane Smith</td>
                            <td>456 Oak Ave</td>
                            <td>Austin</td>
                            <td>TX</td>
                            <td>73301</td>
                            <td>555-0200</td>
                            <td>parcel</td>
                            <td>1.0</td>
                            <td>high</td>
                            <td>Call on arrival</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
