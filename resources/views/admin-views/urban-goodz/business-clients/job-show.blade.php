@extends('layouts.admin.app')

@section('title', $job->job_number)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ $job->job_number }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.business-clients.jobs', $client->id) }}" class="btn btn--secondary">{{ translate('Back') }}</a>
            </div>
        </div>

        @php
            $statusBadgeMap = [
                'submitted' => 'badge-soft-info', 'under_review' => 'badge-soft-warning',
                'accepted' => 'badge-soft-primary', 'quoted' => 'badge-soft-warning',
                'quote_accepted' => 'badge-soft-primary', 'assigned' => 'badge-soft-info',
                'driver_en_route' => 'badge-soft-info', 'picked_up' => 'badge-soft-warning',
                'in_transit' => 'badge-soft-warning', 'delayed' => 'badge-soft-danger',
                'delivered' => 'badge-soft-success', 'completed' => 'badge-soft-success',
                'invoiced' => 'badge-soft-dark', 'paid' => 'badge-soft-success',
                'canceled' => 'badge-soft-secondary',
            ];
        @endphp

        <div class="row g-2 mb-3">
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Status') }}</small>
                    <div><span class="badge {{ $statusBadgeMap[$job->status] ?? 'badge-soft-info' }}">{{ str_replace('_', ' ', $job->status) }}</span></div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Type') }}</small>
                    <div><span class="badge badge-soft-info">{{ str_replace('_', ' ', $job->job_type) }}</span></div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Amount') }}</small>
                    <div class="font-weight-bold">{{ $job->quoted_amount ? '$' . number_format($job->quoted_amount, 2) : '—' }}</div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Driver') }}</small>
                    <div>{{ $job->assignedDriver ? $job->assignedDriver->f_name . ' ' . $job->assignedDriver->l_name : translate('Unassigned') }}</div>
                </div></div>
            </div>
            <div class="col-md-4 col-12">
                <div class="card"><div class="card-body py-3">
                    <small class="text-muted">{{ translate('Update Status') }}</small>
                    <form method="POST" action="{{ route('admin.urban-goodz.business-clients.job-status', [$client->id, $job->id]) }}" class="form-inline mt-1">
                        @csrf
                        <select name="status" class="form-control form-control-sm mr-2">
                            @foreach(['submitted', 'under_review', 'accepted', 'quoted', 'quote_accepted', 'assigned', 'driver_en_route', 'picked_up', 'in_transit', 'delivered', 'completed', 'canceled'] as $s)
                                <option value="{{ $s }}" {{ $job->status === $s ? 'selected' : '' }}>{{ str_replace('_', ' ', $s) }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn--primary" type="submit">{{ translate('Update') }}</button>
                    </form>
                </div></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Assign Driver') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.business-clients.job-assign-driver', [$client->id, $job->id]) }}" class="form-inline">
                            @csrf
                            <select name="assigned_delivery_man_id" class="form-control form-control-sm mr-2">
                                <option value="">{{ translate('Select Driver') }}</option>
                                @foreach($drivers as $d)
                                    <option value="{{ $d->id }}" {{ $job->assigned_delivery_man_id === $d->id ? 'selected' : '' }}>{{ $d->f_name }} {{ $d->l_name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-sm btn--primary" type="submit">{{ translate('Assign') }}</button>
                        </form>
                    </div>
                </div>
                @if($job->status === 'submitted' || $job->status === 'under_review')
                <div class="card mt-3">
                    <div class="card-header"><h5>{{ translate('Provide Quote') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.business-clients.job-quote', [$client->id, $job->id]) }}">
                            @csrf
                            <div class="form-group">
                                <label>{{ translate('Quoted Amount') }} ($)</label>
                                <input type="number" step="0.01" name="quoted_amount" class="form-control" required value="{{ $job->quoted_amount }}">
                            </div>
                            <div class="form-group">
                                <label>{{ translate('Notes') }}</label>
                                <textarea name="admin_notes" class="form-control" rows="2">{{ $job->admin_notes }}</textarea>
                            </div>
                            <button class="btn btn--primary" type="submit">{{ translate('Submit Quote') }}</button>
                        </form>
                    </div>
                </div>
                @endif
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Job Details') }}</h5></div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">{{ translate('Reference') }}</dt>
                            <dd class="col-sm-8">{{ $job->reference_number ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('PO Number') }}</dt>
                            <dd class="col-sm-8">{{ $job->po_number ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Description') }}</dt>
                            <dd class="col-sm-8">{{ $job->description ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Load Type') }}</dt>
                            <dd class="col-sm-8">{{ $job->load_type ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Weight') }}</dt>
                            <dd class="col-sm-8">{{ $job->weight ? $job->weight . ' ' . $job->weight_unit : translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Dimensions') }}</dt>
                            <dd class="col-sm-8">{{ $job->dimensions ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Pallet Count') }}</dt>
                            <dd class="col-sm-8">{{ $job->pallet_count ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Vehicle Type') }}</dt>
                            <dd class="col-sm-8">{{ $job->vehicle_type_needed ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Special Handling') }}</dt>
                            <dd class="col-sm-8">{{ $job->special_handling ?? translate('N/A') }}</dd>
                            @if($job->job_type === 'medical_courier')
                            <dt class="col-sm-4">{{ translate('Specimen Type') }}</dt>
                            <dd class="col-sm-8">{{ $job->specimen_type ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Temperature') }}</dt>
                            <dd class="col-sm-8">{{ $job->temperature_requirement ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Urgency') }}</dt>
                            <dd class="col-sm-8">{{ $job->urgency_level ?? translate('N/A') }}</dd>
                            <dt class="col-sm-4">{{ translate('Chain of Custody') }}</dt>
                            <dd class="col-sm-8">{{ $job->chain_of_custody_required ? translate('Yes') : translate('No') }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
                @if($job->admin_notes)
                <div class="card mt-3">
                    <div class="card-header"><h5>{{ translate('Admin Notes') }}</h5></div>
                    <div class="card-body">
                        <p class="mb-0">{{ $job->admin_notes }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection
