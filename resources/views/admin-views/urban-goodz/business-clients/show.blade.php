@extends('layouts.admin.app')

@section('title', $client->company_name)

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h1 class="page-header-title">{{ $client->company_name }}</h1>
                <div>
                    @if(auth('admin')->user()->role_id == 1)
                    <form action="{{ route('admin.urban-goodz.business-clients.impersonate', $client->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="mode" value="read_only">
                        <button type="submit" class="btn btn-sm btn-outline-primary" title="Open Business Portal (Read-Only)">
                            <i class="tio-open-in-new mr-1"></i> Open Business Portal
                        </button>
                    </form>
                    <form action="{{ route('admin.urban-goodz.business-clients.impersonate', $client->id) }}" method="POST" style="display:inline;">
                        @csrf
                        <input type="hidden" name="mode" value="manage">
                        <button type="submit" class="btn btn-sm btn-outline-warning ml-1" title="Manage as Business (Writes Logged)"
                            onclick="return confirm('This will let you perform write actions as this business. All actions are logged. Continue?')">
                            <i class="tio-settings mr-1"></i> Manage as Business
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('admin.urban-goodz.business-clients.edit', $client->id) }}" class="btn btn--primary">
                        <i class="tio-edit"></i> {{ translate('Edit') }}
                    </a>
                    <a href="{{ route('admin.urban-goodz.business-clients.index') }}" class="btn btn--secondary">
                        <i class="tio-back"></i> {{ translate('Back') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Status') }}</small>
                    <div>
                        @php $statusColor = ['pending'=>'warning', 'approved'=>'success', 'suspended'=>'danger', 'inactive'=>'secondary']; @endphp
                        <span class="badge badge-soft-{{ $statusColor[$client->status] ?? 'info' }}">{{ $client->status }}</span>
                    </div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Users') }}</small>
                    <div class="font-weight-bold h4 mb-0">
                        <a href="{{ route('admin.urban-goodz.business-clients.users.index', $client->id) }}" class="text-dark">{{ $client->users->count() }}</a>
                    </div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Locations') }}</small>
                    <div class="font-weight-bold h4 mb-0">
                        <a href="{{ route('admin.urban-goodz.business-clients.locations.index', $client->id) }}" class="text-dark">{{ $client->locations->count() }}</a>
                    </div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Documents') }}</small>
                    <div class="font-weight-bold h4 mb-0">
                        <a href="{{ route('admin.urban-goodz.business-clients.documents.index', $client->id) }}" class="text-dark">{{ $client->documents->count() }}</a>
                    </div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Jobs') }}</small>
                    <div class="font-weight-bold h4 mb-0">
                        <a href="{{ route('admin.urban-goodz.business-clients.jobs', $client->id) }}" class="text-dark">{{ $client->jobs->count() }}</a>
                    </div>
                </div></div>
            </div>
            <div class="col-md-2 col-6">
                <div class="card"><div class="card-body text-center py-3">
                    <small class="text-muted">{{ translate('Billing') }}</small>
                    <div><span class="badge badge-soft-{{ $client->billing_terms === 'prepaid' ? 'success' : 'info' }}">{{ str_replace('_', ' ', $client->billing_terms ?? 'due_on_receipt') }}</span></div>
                </div></div>
            </div>
        </div>

        <div class="nav-tabs-wrapper mb-3">
            <ul class="nav nav-tabs nav-fill border-0">
                <li class="nav-item"><a class="nav-link active" href="#overview" data-toggle="tab">{{ translate('Overview') }}</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.urban-goodz.business-clients.users.index', $client->id) }}">{{ translate('Users') }} ({{ $client->users->count() }})</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.urban-goodz.business-clients.locations.index', $client->id) }}">{{ translate('Locations') }} ({{ $client->locations->count() }})</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.urban-goodz.business-clients.documents.index', $client->id) }}">{{ translate('Documents') }} ({{ $client->documents->count() }})</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('admin.urban-goodz.business-clients.jobs', $client->id) }}">{{ translate('Jobs') }}</a></li>
            </ul>
        </div>

        <div class="tab-content">
            <div class="tab-pane active" id="overview">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h5>{{ translate('Company Info') }}</h5></div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">{{ translate('Company Name') }}</dt>
                                    <dd class="col-sm-8">{{ $client->company_name }}</dd>
                                    <dt class="col-sm-4">{{ translate('Legal Name') }}</dt>
                                    <dd class="col-sm-8">{{ $client->legal_name ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Contact') }}</dt>
                                    <dd class="col-sm-8">{{ $client->contact_name ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Business Type') }}</dt>
                                    <dd class="col-sm-8">{{ $client->business_type ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Email') }}</dt>
                                    <dd class="col-sm-8">{{ $client->email }}</dd>
                                    <dt class="col-sm-4">{{ translate('Contact Email') }}</dt>
                                    <dd class="col-sm-8">{{ $client->contact_email ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Phone') }}</dt>
                                    <dd class="col-sm-8">{{ $client->phone ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Website') }}</dt>
                                    <dd class="col-sm-8">{{ $client->website ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Tax ID') }}</dt>
                                    <dd class="col-sm-8">{{ $client->tax_id ?? translate('N/A') }}</dd>
                                </dl>
                            </div>
                        </div>
                        @if(in_array($client->status, ['pending', 'suspended']))
                        <div class="card mt-3">
                            <div class="card-body">
                                @if($client->status === 'pending')
                                    <form method="POST" action="{{ route('admin.urban-goodz.business-clients.approve', $client->id) }}">
                                        @csrf
                                        <div class="form-group">
                                            <label>{{ translate('Approval Notes') }}</label>
                                            <textarea name="notes" class="form-control" rows="2">{{ $client->notes }}</textarea>
                                        </div>
                                        <button class="btn btn--primary" type="submit">{{ translate('Approve Client') }}</button>
                                    </form>
                                @elseif($client->status === 'suspended')
                                    <form method="POST" action="{{ route('admin.urban-goodz.business-clients.reactivate', $client->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn--primary" type="submit">{{ translate('Reactivate Client') }}</button>
                                    </form>
                                @endif
                                @if($client->status !== 'suspended')
                                    <form method="POST" action="{{ route('admin.urban-goodz.business-clients.suspend', $client->id) }}" class="d-inline ml-2">
                                        @csrf
                                        <button class="btn btn--danger" type="submit" onclick="return confirm('{{ translate('Suspend this client?') }}')">{{ translate('Suspend') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h5>{{ translate('Address') }}</h5></div>
                            <div class="card-body">
                                <p class="mb-0">
                                    {{ $client->address ?? translate('No address on file') }}<br>
                                    @if($client->city){{ $client->city }}, @endif
                                    @if($client->state){{ $client->state }} @endif
                                    {{ $client->postal_code ?? '' }}<br>
                                    {{ $client->country ?? 'US' }}
                                </p>
                            </div>
                        </div>
                        <div class="card mt-3">
                            <div class="card-header"><h5>{{ translate('Billing') }}</h5></div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-4">{{ translate('Billing Email') }}</dt>
                                    <dd class="col-sm-8">{{ $client->billing_email ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Billing Phone') }}</dt>
                                    <dd class="col-sm-8">{{ $client->billing_phone ?? translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Terms') }}</dt>
                                    <dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $client->billing_terms ?? 'due_on_receipt')) }}</dd>
                                    <dt class="col-sm-4">{{ translate('Credit Limit') }}</dt>
                                    <dd class="col-sm-8">{{ $client->credit_limit ? '$' . number_format($client->credit_limit, 2) : translate('N/A') }}</dd>
                                    <dt class="col-sm-4">{{ translate('Payment Method') }}</dt>
                                    <dd class="col-sm-8">
                                        @php $pmsColor = ['not_added'=>'secondary', 'pending'=>'warning', 'verified'=>'success', 'failed'=>'danger', 'disabled'=>'dark']; @endphp
                                        <span class="badge badge-soft-{{ $pmsColor[$client->payment_method_status] ?? 'secondary' }}">{{ str_replace('_', ' ', $client->payment_method_status ?? 'not_added') }}</span>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                        @if($client->notes)
                        <div class="card mt-3">
                            <div class="card-header"><h5>{{ translate('Notes') }}</h5></div>
                            <div class="card-body"><p class="mb-0">{{ $client->notes }}</p></div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
