@extends('layouts.admin.app')

@section('title', translate('Creator Business Leads'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('Creator Business Leads') }}</h1></div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search business, category or city') }}" value="{{ request('search') }}"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>{{ translate('New') }}</option>
                            <option value="researching" {{ request('status') == 'researching' ? 'selected' : '' }}>{{ translate('Researching') }}</option>
                            <option value="contacted" {{ request('status') == 'contacted' ? 'selected' : '' }}>{{ translate('Contacted') }}</option>
                            <option value="onboarding" {{ request('status') == 'onboarding' ? 'selected' : '' }}>{{ translate('Onboarding') }}</option>
                            <option value="onboarded" {{ request('status') == 'onboarded' ? 'selected' : '' }}>{{ translate('Onboarded') }}</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="suggested_module" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Modules') }}</option>
                            <option value="order_anywhere" {{ request('suggested_module') == 'order_anywhere' ? 'selected' : '' }}>{{ translate('Order Anywhere') }}</option>
                            <option value="rentals" {{ request('suggested_module') == 'rentals' ? 'selected' : '' }}>{{ translate('Rentals') }}</option>
                            <option value="logistics" {{ request('suggested_module') == 'logistics' ? 'selected' : '' }}>{{ translate('Logistics') }}</option>
                            <option value="events" {{ request('suggested_module') == 'events' ? 'selected' : '' }}>{{ translate('Events') }}</option>
                            <option value="fashion_fit" {{ request('suggested_module') == 'fashion_fit' ? 'selected' : '' }}>{{ translate('Fashion Fit') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Business') }}</th><th>{{ translate('Category') }}</th><th>{{ translate('City') }}</th><th>{{ translate('Creator') }}</th><th>{{ translate('Module') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($leads as $lead)
                        <tr>
                            <td>{{ $lead->business_name }}</td>
                            <td>{{ $lead->category ?? 'N/A' }}</td>
                            <td>{{ $lead->city ?? 'N/A' }}</td>
                            <td>{{ $lead->profile->display_name ?? 'N/A' }}</td>
                            <td><span class="badge badge-soft-info">{{ $lead->suggested_module ?? 'N/A' }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('admin.urban-goodz.creator.leads.status', $lead->id) }}" class="d-inline">
                                    @csrf
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="new" {{ $lead->status == 'new' ? 'selected' : '' }}>{{ translate('New') }}</option>
                                        <option value="researching" {{ $lead->status == 'researching' ? 'selected' : '' }}>{{ translate('Researching') }}</option>
                                        <option value="contacted" {{ $lead->status == 'contacted' ? 'selected' : '' }}>{{ translate('Contacted') }}</option>
                                        <option value="onboarding" {{ $lead->status == 'onboarding' ? 'selected' : '' }}>{{ translate('Onboarding') }}</option>
                                        <option value="onboarded" {{ $lead->status == 'onboarded' ? 'selected' : '' }}>{{ translate('Onboarded') }}</option>
                                        <option value="rejected" {{ $lead->status == 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                                    </select>
                                </form>
                            </td>
                            <td>{{ $lead->created_at->format('M d, Y') }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-ghost-info" data-toggle="modal" data-target="#leadNotesModal{{ $lead->id }}">
                                    <i class="tio-edit"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">{{ translate('No leads found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($leads->hasPages())
                <div class="card-footer">{{ $leads->links() }}</div>
            @endif
        </div>
    </div>

    @foreach($leads as $lead)
        <div class="modal fade" id="leadNotesModal{{ $lead->id }}">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('admin.urban-goodz.creator.leads.status', $lead->id) }}">
                    @csrf
                    <div class="modal-header"><h5>{{ $lead->business_name }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ translate('Admin Notes') }}</label>
                            <textarea name="admin_notes" class="form-control" rows="3">{{ $lead->admin_notes }}</textarea>
                        </div>
                        <p><strong>{{ translate('Business') }}:</strong> {{ $lead->business_name }}</p>
                        <p><strong>{{ translate('Phone') }}:</strong> {{ $lead->phone ?? 'N/A' }}</p>
                        <p><strong>{{ translate('Address') }}:</strong> {{ $lead->address ?? 'N/A' }}</p>
                        <p><strong>{{ translate('Creator') }}:</strong> {{ $lead->profile->display_name ?? 'N/A' }}</p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="status" value="{{ $lead->status }}">
                        <button type="submit" class="btn btn-primary">{{ translate('Save Notes') }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    </div>
                </form>
            </div></div>
        </div>
    @endforeach
@endsection
