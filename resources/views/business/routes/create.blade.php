@extends('business.layouts.app')

@section('title', translate('Create Route'))

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">{{ translate('Create Courier Route') }}</h1>
    </div>

    @if($locations->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5">
            <h5 style="color: var(--ug-black); font-weight: 600;">{{ translate('Add a location before creating a route') }}</h5>
            <p class="text-muted mb-3" style="color: #6c757d !important; max-width: 450px; margin: 0 auto 1rem;">
                {{ translate('Routes require a pickup location. Add a location first, then return here to create your route.') }}
            </p>
            <a href="{{ route('business.locations.create') }}" class="btn btn--primary">
                {{ translate('Add Location') }}
            </a>
        </div>
    </div>
    @else
    <form action="{{ route('business.routes.store') }}" method="POST" id="route-form">
        @csrf

        <div class="card">
            <div class="card-header"><h5>{{ translate('Route Information') }}</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="route_name">{{ translate('Route Name') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="route_name" id="route_name" required value="{{ old('route_name') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="route_type">{{ translate('Route Type') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="route_type" id="route_type" required>
                                <option value="">{{ translate('Select type') }}</option>
                                <option value="logistics" {{ old('route_type') === 'logistics' ? 'selected' : '' }}>{{ translate('Logistics') }}</option>
                                <option value="medical_courier" {{ old('route_type') === 'medical_courier' ? 'selected' : '' }}>{{ translate('Medical Courier') }}</option>
                                <option value="load_board" {{ old('route_type') === 'load_board' ? 'selected' : '' }}>{{ translate('Load Board') }}</option>
                                <option value="bulk_delivery" {{ old('route_type') === 'bulk_delivery' ? 'selected' : '' }}>{{ translate('Bulk Delivery') }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="pickup_location">{{ translate('Pickup Location') }} <span class="text-danger">*</span></label>
                            <select class="form-control" name="pickup_location" id="pickup_location" required>
                                <option value="">{{ translate('Select a location') }}</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location->name }} - {{ $location->address }}" data-lat="{{ $location->latitude }}" data-lng="{{ $location->longitude }}" {{ old('pickup_location') === $location->name . ' - ' . $location->address ? 'selected' : '' }}>{{ $location->name }} - {{ $location->city }}, {{ $location->state }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="pickup_lat" id="pickup_lat" value="{{ old('pickup_lat') }}">
                            <input type="hidden" name="pickup_lng" id="pickup_lng" value="{{ old('pickup_lng') }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label" for="scheduled_date">{{ translate('Scheduled Date') }} <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="scheduled_date" id="scheduled_date" required value="{{ old('scheduled_date') }}">
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label class="input-label" for="end_location">{{ translate('End Location (Optional)') }}</label>
                            <select class="form-control" name="end_location" id="end_location">
                                <option value="">{{ translate('No fixed endpoint') }}</option>
                                @foreach($locations as $location)
                                    @php($endValue = $location->name.' - '.$location->address)
                                    <option value="{{ $endValue }}" data-lat="{{ $location->latitude }}" data-lng="{{ $location->longitude }}" @selected(old('end_location') === $endValue)>{{ $location->name }} — {{ $location->city }}, {{ $location->state }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="end_lat" id="end_lat" value="{{ old('end_lat') }}">
                            <input type="hidden" name="end_lng" id="end_lng" value="{{ old('end_lng') }}">
                            <label class="mt-2"><input type="checkbox" name="return_to_origin" value="1" @checked(old('return_to_origin'))> {{ translate('Return to pickup location') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h5>{{ translate('Drop-off Stops') }} <span class="text-danger">*</span></h5>
                <button type="button" class="btn btn--primary" id="add-stop-btn" onclick="addStop()" style="font-weight: 600;">
                    <i class="tio-add"></i> {{ translate('Add Stop') }}
                </button>
            </div>
            <div class="card-body" id="stops-wrapper">
                <div id="stops-container">
                    @php($oldStops = old('stops', [['dropoff_address' => '', 'recipient_name' => '', 'contact_phone' => '', 'delivery_notes' => '']]))
                    @foreach($oldStops as $index => $stop)
                    <div class="stop-row card card-border mb-2 {{ $index > 0 ? 'border' : '' }}" data-index="{{ $index }}">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0 stop-label">{{ translate('Stop') }} {{ $loop->iteration }}</h6>
                                @if($index > 0)
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStop(this)">
                                    <i class="tio-delete"></i>
                                </button>
                                @endif
                            </div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label class="small">{{ translate('Recipient Name') }}</label>
                                        <input type="text" class="form-control form-control-sm" name="stops[{{ $index }}][recipient_name]" value="{{ $stop['recipient_name'] ?? '' }}" placeholder="{{ translate('Business or person name') }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label class="small">{{ translate('Contact Phone') }}</label>
                                        <input type="text" class="form-control form-control-sm" name="stops[{{ $index }}][contact_phone]" value="{{ $stop['contact_phone'] ?? '' }}" placeholder="{{ translate('Phone number') }}">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group mb-0">
                                        <label class="small">{{ translate('Package Type') }}</label>
                                        <select class="form-control form-control-sm" name="stops[{{ $index }}][package_type]">
                                            <option value="parcel" {{ ($stop['package_type'] ?? 'parcel') === 'parcel' ? 'selected' : '' }}>{{ translate('Parcel') }}</option>
                                            <option value="document" {{ ($stop['package_type'] ?? '') === 'document' ? 'selected' : '' }}>{{ translate('Document') }}</option>
                                            <option value="envelope" {{ ($stop['package_type'] ?? '') === 'envelope' ? 'selected' : '' }}>{{ translate('Envelope') }}</option>
                                            <option value="specimen" {{ ($stop['package_type'] ?? '') === 'specimen' ? 'selected' : '' }}>{{ translate('Specimen') }}</option>
                                            <option value="supply" {{ ($stop['package_type'] ?? '') === 'supply' ? 'selected' : '' }}>{{ translate('Supply') }}</option>
                                            <option value="pallet" {{ ($stop['package_type'] ?? '') === 'pallet' ? 'selected' : '' }}>{{ translate('Pallet') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="small">{{ translate('Drop-off Address') }} <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-sm" name="stops[{{ $index }}][dropoff_address]" value="{{ $stop['dropoff_address'] ?? '' }}" placeholder="123 Main St, City, State ZIP" {{ $index === 0 ? 'required' : '' }}>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small">{{ translate('Latitude') }}</label>
                                    <input type="number" step="0.0000001" min="-90" max="90" class="form-control form-control-sm" name="stops[{{ $index }}][dropoff_lat]" value="{{ $stop['dropoff_lat'] ?? '' }}" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="small">{{ translate('Longitude') }}</label>
                                    <input type="number" step="0.0000001" min="-180" max="180" class="form-control form-control-sm" name="stops[{{ $index }}][dropoff_lng]" value="{{ $stop['dropoff_lng'] ?? '' }}" required>
                                </div>
                                <div class="col-12">
                                    <div class="form-group mb-0">
                                        <label class="small">{{ translate('Delivery Notes') }}</label>
                                        <textarea class="form-control form-control-sm" name="stops[{{ $index }}][delivery_notes]" rows="1" placeholder="{{ translate('Leave at door, call on arrival, etc.') }}">{{ $stop['delivery_notes'] ?? '' }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <p class="text-muted small mb-0 mt-2" style="color: #6c757d !important;">
                    <i class="tio-info"></i> {{ translate('Add at least one drop-off stop. You can add more stops after creating the route.') }}
                </p>
            </div>
        </div>

        <div class="d-flex justify-content-end mt-3 gap-2">
            <a href="{{ route('business.routes.index') }}" class="btn btn-secondary">{{ translate('Cancel') }}</a>
            <button type="button" class="btn btn--primary" onclick="confirmRoute()">{{ translate('Create Route') }}</button>
        </div>
    </form>
    @endif
@endsection

<div class="modal fade" id="routeConfirmModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ translate('Review Route') }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <h6 class="text-muted mb-1">{{ translate('Route') }}: <span id="confirm-route-name" class="text-dark"></span></h6>
                    <h6 class="text-muted mb-1">{{ translate('Type') }}: <span id="confirm-route-type" class="text-dark"></span></h6>
                    <h6 class="text-muted mb-1">{{ translate('Pickup') }}: <span id="confirm-pickup" class="text-dark"></span></h6>
                    <h6 class="text-muted mb-3">{{ translate('Date') }}: <span id="confirm-date" class="text-dark"></span></h6>
                </div>
                <hr>
                <h6>{{ translate('Drop-off Stops') }} ({{ translate('total') }}: <span id="confirm-stop-count">0</span>)</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th>{{ translate('Address') }}</th>
                                <th>{{ translate('Recipient') }}</th>
                                <th>{{ translate('Type') }}</th>
                            </tr>
                        </thead>
                        <tbody id="confirm-stops-body"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Go Back') }}</button>
                <button type="button" class="btn btn--primary" onclick="submitRoute()">{{ translate('Confirm & Create Route') }}</button>
            </div>
        </div>
    </div>
</div>

@push('script')
<script>
    let stopIndex = {{ count($oldStops) }};

    function syncRouteLocation(selectId, latId, lngId) {
        const selected = document.getElementById(selectId).selectedOptions[0];
        document.getElementById(latId).value = selected.dataset.lat || '';
        document.getElementById(lngId).value = selected.dataset.lng || '';
    }

    document.getElementById('pickup_location')?.addEventListener('change', () => syncRouteLocation('pickup_location', 'pickup_lat', 'pickup_lng'));
    document.getElementById('end_location')?.addEventListener('change', () => syncRouteLocation('end_location', 'end_lat', 'end_lng'));

    function addStop() {
        const container = document.getElementById('stops-container');
        const idx = stopIndex++;
        const div = document.createElement('div');
        div.className = 'stop-row card card-border mb-2 border';
        div.dataset.index = idx;
        div.innerHTML = `
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0 stop-label">${'{{ translate('Stop') }}'} ${document.querySelectorAll('.stop-row').length + 1}</h6>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeStop(this)">
                        <i class="tio-delete"></i>
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="small">${'{{ translate('Recipient Name') }}'}</label>
                            <input type="text" class="form-control form-control-sm" name="stops[${idx}][recipient_name]" placeholder="${'{{ translate('Business or person name') }}'}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="small">${'{{ translate('Contact Phone') }}'}</label>
                            <input type="text" class="form-control form-control-sm" name="stops[${idx}][contact_phone]" placeholder="${'{{ translate('Phone number') }}'}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group mb-0">
                            <label class="small">${'{{ translate('Package Type') }}'}</label>
                            <select class="form-control form-control-sm" name="stops[${idx}][package_type]">
                                <option value="parcel">${'{{ translate('Parcel') }}'}</option>
                                <option value="document">${'{{ translate('Document') }}'}</option>
                                <option value="envelope">${'{{ translate('Envelope') }}'}</option>
                                <option value="specimen">${'{{ translate('Specimen') }}'}</option>
                                <option value="supply">${'{{ translate('Supply') }}'}</option>
                                <option value="pallet">${'{{ translate('Pallet') }}'}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label class="small">${'{{ translate('Drop-off Address') }}'} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" name="stops[${idx}][dropoff_address]" placeholder="123 Main St, City, State ZIP" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="small">${'{{ translate('Latitude') }}'}</label>
                        <input type="number" step="0.0000001" min="-90" max="90" class="form-control form-control-sm" name="stops[${idx}][dropoff_lat]" required>
                    </div>
                    <div class="col-md-6">
                        <label class="small">${'{{ translate('Longitude') }}'}</label>
                        <input type="number" step="0.0000001" min="-180" max="180" class="form-control form-control-sm" name="stops[${idx}][dropoff_lng]" required>
                    </div>
                    <div class="col-12">
                        <div class="form-group mb-0">
                            <label class="small">${'{{ translate('Delivery Notes') }}'}</label>
                            <textarea class="form-control form-control-sm" name="stops[${idx}][delivery_notes]" rows="1" placeholder="${'{{ translate('Leave at door, call on arrival, etc.') }}'}"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
        updateStopLabels();
    }

    function removeStop(btn) {
        const row = btn.closest('.stop-row');
        if (document.querySelectorAll('.stop-row').length <= 1) return;
        row.remove();
        updateStopLabels();
    }

    function updateStopLabels() {
        document.querySelectorAll('.stop-row').forEach((el, i) => {
            const label = el.querySelector('.stop-label');
            if (label) label.textContent = '{{ translate('Stop') }} ' + (i + 1);
        });
    }

    function confirmRoute() {
        const routeName = document.getElementById('route_name').value.trim();
        const routeType = document.getElementById('route_type').value;
        const pickup = document.getElementById('pickup_location').value;
        const date = document.getElementById('scheduled_date').value;

        if (!routeName || !routeType || !pickup || !date) {
            toastr.error('{{ translate('Please fill in all route details first') }}');
            return;
        }

        const rows = document.querySelectorAll('.stop-row');
        if (rows.length === 0) {
            toastr.error('{{ translate('Add at least one drop-off stop') }}');
            return;
        }

        document.getElementById('confirm-route-name').textContent = routeName;
        document.getElementById('confirm-route-type').textContent = routeType.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        document.getElementById('confirm-pickup').textContent = pickup;
        document.getElementById('confirm-date').textContent = date;
        document.getElementById('confirm-stop-count').textContent = rows.length;

        const tbody = document.getElementById('confirm-stops-body');
        tbody.innerHTML = '';
        rows.forEach((row, i) => {
            const addr = row.querySelector('[name$="[dropoff_address]"]').value || '-';
            const name = row.querySelector('[name$="[recipient_name]"]').value || '-';
            const ptype = row.querySelector('[name$="[package_type]"]')?.value || 'parcel';
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="text-center">${i + 1}</td>
                <td>${addr}</td>
                <td>${name}</td>
                <td><span class="badge badge-soft-info">${ptype}</span></td>
            `;
            tbody.appendChild(tr);
        });

        $('#routeConfirmModal').modal('show');
    }

    function submitRoute() {
        $('#routeConfirmModal').modal('hide');
        document.getElementById('route-form').submit();
    }
</script>
@endpush
