@extends('layouts.admin.app')

@section('title', translate('Campaign: ') . $campaign->title)

@section('content')
    <div class="content container-fluid">
        <div class="page-header d-flex justify-content-between">
            <h1>{{ $campaign->title }}</h1>
            <div>
                <a href="{{ route('admin.urban-goodz.creator.campaigns') }}" class="btn btn-secondary"><i class="tio-back"></i> {{ translate('Back') }}</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><h5>{{ translate('Campaign Details') }}</h5></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.creator.campaigns.update', $campaign->id) }}">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group"><label>{{ translate('Title') }}</label><input type="text" name="title" class="form-control" value="{{ $campaign->title }}" required></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group"><label>{{ translate('Type') }}</label><input type="text" class="form-control" value="{{ $campaign->type }}" readonly></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group"><label>{{ translate('Category') }}</label><input type="text" name="category" class="form-control" value="{{ $campaign->category }}"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group"><label>{{ translate('Vendor') }}</label>
                                        <select name="vendor_id" class="form-control">
                                            <option value="">{{ translate('None') }}</option>
                                            @foreach(\App\Models\Vendor::select('id', 'name')->orderBy('name')->get() as $v)
                                                <option value="{{ $v->id }}" {{ $campaign->vendor_id == $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>{{ translate('City') }}</label><input type="text" name="city" class="form-control" value="{{ $campaign->city }}"></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>{{ translate('Zone') }}</label><input type="text" name="zone" class="form-control" value="{{ $campaign->zone }}"></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>{{ translate('Pay Type') }}</label>
                                        <select name="pay_type" class="form-control">
                                            <option value="flat" {{ $campaign->pay_type == 'flat' ? 'selected' : '' }}>{{ translate('Flat') }}</option>
                                            <option value="commission" {{ $campaign->pay_type == 'commission' ? 'selected' : '' }}>{{ translate('Commission') }}</option>
                                            <option value="hybrid" {{ $campaign->pay_type == 'hybrid' ? 'selected' : '' }}>{{ translate('Hybrid') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>{{ translate('Flat Payout') }}</label><input type="number" name="flat_payout" class="form-control" step="0.01" value="{{ $campaign->flat_payout }}"></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>{{ translate('Commission %') }}</label><input type="number" name="commission_rate" class="form-control" step="0.01" value="{{ $campaign->commission_rate }}"></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group"><label>{{ translate('Deadline') }}</label><input type="date" name="deadline" class="form-control" value="{{ $campaign->deadline?->format('Y-m-d') }}"></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group"><label>{{ translate('Status') }}</label>
                                        <select name="status" class="form-control">
                                            <option value="draft" {{ $campaign->status == 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                                            <option value="open" {{ $campaign->status == 'open' ? 'selected' : '' }}>{{ translate('Open') }}</option>
                                            <option value="assigned" {{ $campaign->status == 'assigned' ? 'selected' : '' }}>{{ translate('Assigned') }}</option>
                                            <option value="in_progress" {{ $campaign->status == 'in_progress' ? 'selected' : '' }}>{{ translate('In Progress') }}</option>
                                            <option value="completed" {{ $campaign->status == 'completed' ? 'selected' : '' }}>{{ translate('Completed') }}</option>
                                            <option value="cancelled" {{ $campaign->status == 'cancelled' ? 'selected' : '' }}>{{ translate('Cancelled') }}</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group"><label>{{ translate('Brief') }}</label><textarea name="brief" class="form-control" rows="3">{{ $campaign->brief }}</textarea></div>
                                </div>
                                <div class="col-md-12">
                                    <div class="form-group"><label>{{ translate('Deliverables') }}</label><textarea name="deliverables" class="form-control" rows="2">{{ $campaign->deliverables }}</textarea></div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">{{ translate('Update Campaign') }}</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <h5>{{ translate('Assigned Creators') }} ({{ $campaign->assignments_count }})</h5>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#assignCreatorModal"><i class="tio-add"></i></button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm m-0">
                            <thead><tr><th>{{ translate('Creator') }}</th><th>{{ translate('Status') }}</th></tr></thead>
                            <tbody>
                            @forelse($campaign->assignments as $a)
                                <tr>
                                    <td>{{ $a->profile->display_name ?? $a->application->creator_name ?? 'N/A' }}</td>
                                    <td><span class="badge badge-{{ $a->approval_status == 'approved' ? 'success' : 'warning' }}">{{ $a->approval_status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center">{{ translate('No creators assigned') }}</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="assignCreatorModal">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('admin.urban-goodz.creator.campaigns.assign', $campaign->id) }}">
                @csrf
                <div class="modal-header"><h5>{{ translate('Assign Creator') }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>{{ translate('Select Creator') }}</label>
                        <select name="creator_profile_id" class="form-control" required>
                            @foreach($availableCreators as $c)
                                <option value="{{ $c->id }}">{{ $c->display_name }} (@{{ $c->handle }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">{{ translate('Assign') }}</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Cancel') }}</button>
                </div>
            </form>
        </div></div>
    </div>
@endsection
