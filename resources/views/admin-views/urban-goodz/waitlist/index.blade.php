@extends('layouts.admin.app')

@section('title', translate('Website Waitlist'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('Website Waitlist') }}</h1></div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-3"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search name, email, phone or city') }}" value="{{ request('search') }}"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>{{ translate('New') }}</option>
                            <option value="contacted" {{ request('status') == 'contacted' ? 'selected' : '' }}>{{ translate('Contacted') }}</option>
                            <option value="onboarded" {{ request('status') == 'onboarded' ? 'selected' : '' }}>{{ translate('Onboarded') }}</option>
                            <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>{{ translate('Archived') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="interest" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Interests') }}</option>
                            <option value="app" {{ request('interest') == 'app' ? 'selected' : '' }}>{{ translate('App / Ordering') }}</option>
                            <option value="business" {{ request('interest') == 'business' ? 'selected' : '' }}>{{ translate('Business') }}</option>
                            <option value="driver" {{ request('interest') == 'driver' ? 'selected' : '' }}>{{ translate('Driver') }}</option>
                            <option value="samaritan" {{ request('interest') == 'samaritan' ? 'selected' : '' }}>{{ translate('Samaritan') }}</option>
                            <option value="other" {{ request('interest') == 'other' ? 'selected' : '' }}>{{ translate('Other') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3"><div class="d-flex justify-content-between"><strong>{{ translate('Total') }}</strong><span>{{ $totals['all'] }}</span></div></div>
                    <div class="col-md-3"><div class="d-flex justify-content-between"><strong>{{ translate('New') }}</strong><span>{{ $totals['new'] }}</span></div></div>
                    <div class="col-md-3"><div class="d-flex justify-content-between"><strong>{{ translate('Contacted') }}</strong><span>{{ $totals['contacted'] }}</span></div></div>
                    <div class="col-md-3"><div class="d-flex justify-content-between"><strong>{{ translate('Onboarded') }}</strong><span>{{ $totals['onboarded'] }}</span></div></div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Name') }}</th><th>{{ translate('Email') }}</th><th>{{ translate('Phone') }}</th><th>{{ translate('City') }}</th><th>{{ translate('Interest') }}</th><th>{{ translate('Source') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td>{{ $entry->full_name }}</td>
                            <td>{{ $entry->email }}</td>
                            <td>{{ $entry->phone ?? 'N/A' }}</td>
                            <td>{{ $entry->city ?? 'N/A' }}</td>
                            <td><span class="badge badge-soft-info">{{ $entry->interest }}</span></td>
                            <td>{{ $entry->source ?? 'N/A' }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.urban-goodz.waitlist.status', $entry->id) }}" class="d-inline">
                                    @csrf
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="new" {{ $entry->status == 'new' ? 'selected' : '' }}>{{ translate('New') }}</option>
                                        <option value="contacted" {{ $entry->status == 'contacted' ? 'selected' : '' }}>{{ translate('Contacted') }}</option>
                                        <option value="onboarded" {{ $entry->status == 'onboarded' ? 'selected' : '' }}>{{ translate('Onboarded') }}</option>
                                        <option value="archived" {{ $entry->status == 'archived' ? 'selected' : '' }}>{{ translate('Archived') }}</option>
                                    </select>
                                </form>
                            </td>
                            <td>{{ $entry->created_at->format('M d, Y') }}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-ghost-info" data-toggle="modal" data-target="#waitlistNotesModal{{ $entry->id }}">
                                    <i class="tio-edit"></i>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center">{{ translate('No waitlist entries found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($entries->hasPages())
                <div class="card-footer">{{ $entries->links() }}</div>
            @endif
        </div>
    </div>

    @foreach($entries as $entry)
        <div class="modal fade" id="waitlistNotesModal{{ $entry->id }}">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('admin.urban-goodz.waitlist.status', $entry->id) }}">
                    @csrf
                    <div class="modal-header"><h5>{{ $entry->full_name }}</h5><button type="button" class="close" data-dismiss="modal">&times;</button></div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>{{ translate('Admin Notes') }}</label>
                            <textarea name="admin_notes" class="form-control" rows="3">{{ $entry->admin_notes }}</textarea>
                        </div>
                        <p><strong>{{ translate('Message') }}:</strong> {{ $entry->message ?? 'N/A' }}</p>
                        <p><strong>{{ translate('Page') }}:</strong> {{ $entry->page ?? 'N/A' }}</p>
                        <p><strong>{{ translate('IP / UA') }}:</strong> {{ $entry->ip_address ?? 'N/A' }} / {{ $entry->user_agent ?? 'N/A' }}</p>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="status" value="{{ $entry->status }}">
                        <button type="submit" class="btn btn-primary">{{ translate('Save Notes') }}</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ translate('Close') }}</button>
                    </div>
                </form>
            </div></div>
        </div>
    @endforeach
@endsection
