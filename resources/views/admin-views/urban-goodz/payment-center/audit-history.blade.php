@extends('layouts.admin.app')

@section('title', translate('Payment Center Audit History'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span style="color:#ED9914;">{{ translate('Payment Center Audit History') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $audits->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.payment-center.index') }}" class="btn btn--secondary">{{ translate('Back to Payment Center') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('Setting') }}</th>
                            <th>{{ translate('Action') }}</th>
                            <th>{{ translate('Old Value') }}</th>
                            <th>{{ translate('New Value') }}</th>
                            <th>{{ translate('Old Source') }}</th>
                            <th>{{ translate('New Source') }}</th>
                            <th>{{ translate('Changed By') }}</th>
                            <th>{{ translate('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($audits as $key => $audit)
                            <tr>
                                <td>{{ $audits->firstItem() + $key }}</td>
                                <td class="font-weight-bold">{{ $audit->setting_key }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $audit->action === 'create' ? 'success' : 'info' }}">
                                        {{ $audit->action }}
                                    </span>
                                </td>
                                <td>
                                    @if($audit->old_value !== null)
                                        <code>{{ Str::limit($audit->old_value, 50) }}</code>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <code>{{ Str::limit($audit->new_value, 50) }}</code>
                                </td>
                                <td><span class="badge badge-soft-dark">{{ $audit->old_source ?? '—' }}</span></td>
                                <td><span class="badge badge-soft-dark">{{ $audit->new_source ?? '—' }}</span></td>
                                <td>
                                    @if($audit->admin)
                                        {{ $audit->admin->f_name }} {{ $audit->admin->l_name }}
                                    @else
                                        <span class="text-muted">System</span>
                                    @endif
                                </td>
                                <td>{{ $audit->created_at->format('M d, Y H:i:s') }}</td>
                            </tr>
                        @endforeach
                        @if(count($audits) === 0)
                            <tr>
                                <td colspan="9" class="text-center">{{ translate('No audit records found') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $audits->links() }}
            </div>
        </div>
    </div>
@endsection
