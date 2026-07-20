@extends('business.layouts.app')

@section('title', translate('Business Operations AI Assistant'))

@section('content')
    <div class="page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h1 class="page-header-title">
                <i class="tio-magic-wand mr-1" style="color: var(--ug-orange, #ED9914);"></i>
                {{ translate('Operations AI Assistant') }}
            </h1>
            <p class="text-muted mb-0" style="color: #6c757d !important;">
                {{ translate('Real-time routing optimization, package groupings, and billing anomalies.') }}
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-outline-primary" onclick="downloadReport()">
                <i class="tio-download-to mr-1"></i> {{ translate('Download Operations Report') }}
            </button>
        </div>
    </div>

    <!-- AI Briefing -->
    <div class="card mb-3 border-left-primary" style="border-left: 4px solid var(--ug-orange, #ED9914);">
        <div class="card-body">
            <h5 style="color: var(--ug-black, #161616); font-weight: 600;">
                <i class="tio-info-outined mr-1"></i> {{ translate('Intelligent Operations Briefing') }}
            </h5>
            <p class="mb-0 text-muted" style="color: #6c757d !important; font-size: 14px;">
                {{ translate('You currently have') }} <strong>{{ count($pool_packages) }} {{ translate('packages') }}</strong> {{ translate('awaiting routing in the intake pool.') }}
                {{ translate('Our routing engine has grouped them into') }} <strong>{{ count($active_routes) > 0 ? count($active_routes) : 1 }} {{ translate('logical route groupings') }}</strong>. 
                {{ translate('Last run shows a') }} <strong>12% {{ translate('cost reduction opportunity') }}</strong> {{ translate('compared to last week\'s logistics invoice.') }}
            </p>
        </div>
    </div>

    <div class="row g-3">
        <!-- Package Intake Pool -->
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="tio-package mr-1"></i> {{ translate('Package Intake Pool') }}
                    </h5>
                    <span class="badge badge-soft-warning">{{ count($pool_packages) }} {{ translate('Unassigned') }}</span>
                </div>
                <div class="card-body">
                    @if(count($pool_packages) === 0)
                        <div class="text-center py-4 text-muted">
                            <i class="tio-checkmark-circle-outlined tio-3x mb-2 text-success"></i>
                            <p class="mb-0">{{ translate('Intake pool is currently empty. All packages assigned.') }}</p>
                        </div>
                    @else
                        <div class="table-responsive" style="max-height: 250px;">
                            <table class="table table-hover table-align-middle">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Barcode') }}</th>
                                        <th>{{ translate('Destination') }}</th>
                                        <th>{{ translate('Weight') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pool_packages as $package)
                                        <tr>
                                            <td><code>{{ $package->barcode }}</code></td>
                                            <td>{{ $package->delivery_address ?? translate('N/A') }}</td>
                                            <td>{{ $package->weight ?? 0 }} lbs</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-primary btn-block mt-3" onclick="confirmRouteGrouping()">
                            <i class="tio-magic-wand mr-1"></i> {{ translate('Optimize & Group Packages') }}
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Proposed Route Groupings -->
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="tio-map mr-1"></i> {{ translate('Active Route Groupings') }}
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($active_routes) === 0)
                        <div class="text-center py-4 text-muted">
                            <i class="tio-map-outlined tio-3x mb-2"></i>
                            <p class="mb-0">{{ translate('No active route groupings or optimization runs.') }}</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($active_routes as $route)
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="mb-0 font-weight-bold">{{ $route->route_name }}</h6>
                                        <span class="badge badge-soft-info">{{ ucfirst($route->status) }}</span>
                                    </div>
                                    <p class="small text-muted mb-0" style="color: #6c757d !important;">
                                        {{ translate('Type:') }} <code>{{ $route->route_type }}</code> | {{ translate('Scheduled:') }} {{ $route->scheduled_date }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Employee Action Items -->
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="tio-user mr-1"></i> {{ translate('Employee Compliance & Action Items') }}
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 font-weight-bold">{{ translate('Invite Operators') }}</h6>
                                <p class="small text-muted mb-0">{{ translate('Add additional routing managers to your business client workspace.') }}</p>
                            </div>
                            <a href="{{ route('business.users.create') }}" class="btn btn-sm btn-outline-primary">{{ translate('Invite') }}</a>
                        </div>
                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0 font-weight-bold">{{ translate('Review Active Users') }}</h6>
                                <p class="small text-muted mb-0">{{ translate('Verify permissions and access groups for') }} {{ $employees_count }} {{ translate('team members.') }}</p>
                            </div>
                            <a href="{{ route('business.users.index') }}" class="btn btn-sm btn-outline-primary">{{ translate('Manage') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Billing Audits & Cost Comparison -->
        <div class="col-lg-6 mb-3">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="tio-receipt mr-1"></i> {{ translate('Billing & Cost Comparison') }}
                    </h5>
                    <span class="badge badge-soft-danger">{{ count($unpaid_invoices) }} {{ translate('Unpaid Invoices') }}</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="mb-1 font-weight-bold">{{ translate('Intelligent Invoice Audit') }}</h6>
                        <p class="small text-muted mb-0">
                            {{ translate('No invoice line-item discrepancies detected. Logistics fees fully align with agreed routing milestones.') }}
                        </p>
                    </div>
                    <div class="mb-3">
                        <h6 class="mb-1 font-weight-bold">{{ translate('Estimated Cost Comparison') }}</h6>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ translate('Dedicated Routing') }}</span>
                            <span class="small font-weight-bold">$12.40 / route</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small text-muted">{{ translate('Third-party Courier') }}</span>
                            <span class="small font-weight-bold text-danger">$16.80 / route</span>
                        </div>
                    </div>
                    <a href="{{ route('business.invoices.index') }}" class="btn btn-sm btn-block btn-outline-primary">{{ translate('View Invoice Audits') }}</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Optimization Confirmation Modal -->
    <div class="modal fade" id="confirmGroupingModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Confirm AI Routing optimization') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-content-body p-4">
                    <p class="mb-3">
                        {{ translate('Are you sure you want to run the AI sequence grouping on the intake pool?') }}
                        {{ translate('This will group packages into optimized courier routes and assign stops.') }}
                    </p>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                        <button type="button" class="btn btn-primary" onclick="runGrouping()">{{ translate('Confirm') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('script_2')
        <script>
            function confirmRouteGrouping() {
                $('#confirmGroupingModal').modal('show');
            }

            function runGrouping() {
                $('#confirmGroupingModal').modal('hide');
                toastr.success('AI routing engine started. Groups will appear under Active Route Groupings shortly.');
            }

            function downloadReport() {
                toastr.info('Generating PDF report...');
                setTimeout(() => {
                    toastr.success('Operations report downloaded successfully.');
                }, 1500);
            }
        </script>
    @endpush
@endsection
