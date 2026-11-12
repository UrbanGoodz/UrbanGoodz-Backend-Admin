@extends('layouts.admin.app')

@section('title', translate('Order Anywhere Reimbursements'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h1 class="page-header-title">{{ translate('Purchase Reimbursements') }}</h1>
            <a href="{{ route('admin.urban-goodz.order-anywhere.index') }}" class="btn btn-outline-primary">{{ translate('Back to Order Anywhere') }}</a>
        </div>

        @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if(!$enabled)
            <div class="alert alert-soft-warning">
                {{ translate('Reimbursement purchasing is currently OFF. Set URBAN_GOODZ_PURCHASE_METHOD=reimburse to let drivers pay with their own money and claim it back.') }}
            </div>
        @endif

        <ul class="nav nav-tabs mb-3">
            @foreach(['pending', 'approved', 'rejected', 'all'] as $tab)
                <li class="nav-item">
                    <a class="nav-link {{ $status === $tab ? 'active' : '' }}" href="{{ route('admin.urban-goodz.order-anywhere.reimbursements', ['status' => $tab]) }}">{{ ucfirst($tab) }}</a>
                </li>
            @endforeach
        </ul>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('Order') }}</th>
                            <th>{{ translate('Driver') }}</th>
                            <th>{{ translate('Claimed') }}</th>
                            <th>{{ translate('Approved budget') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Receipt') }}</th>
                            <th>{{ translate('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($claims as $c)
                            @php $d = $drivers[$c->delivery_man_id] ?? null; $over = (float) $c->claimed_total > (float) $c->authorized_total + 0.005; @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.order-anywhere.show', $c->order_anywhere_request_id) }}">
                                        {{ $c->orderAnywhereRequest?->request_number ?? '#'.$c->order_anywhere_request_id }}
                                    </a>
                                </td>
                                <td>{{ $d ? $d->f_name.' '.$d->l_name : '#'.$c->delivery_man_id }}</td>
                                <td>
                                    <strong>${{ number_format($c->claimed_total, 2) }}</strong>
                                    @if($over) <span class="badge badge-soft-warning">{{ translate('over budget') }}</span> @endif
                                </td>
                                <td>${{ number_format($c->authorized_total, 2) }} <small class="text-muted">({{ translate('max') }} ${{ number_format($c->cap_total, 2) }})</small></td>
                                <td>
                                    @php $m = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger']; @endphp
                                    <span class="badge badge-soft-{{ $m[$c->status] ?? 'secondary' }}">{{ ucfirst($c->status) }}</span>
                                    @if($c->status === 'approved') <small>${{ number_format($c->approved_total, 2) }}</small> @endif
                                </td>
                                <td><a href="{{ route('admin.urban-goodz.order-anywhere.reimbursements.receipt', $c->id) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">{{ translate('View') }}</a></td>
                                <td style="min-width:280px">
                                    @if($c->status === 'pending')
                                        <form method="POST" action="{{ route('admin.urban-goodz.order-anywhere.reimbursements.approve', $c->id) }}" class="d-flex gap-1 mb-1">
                                            @csrf
                                            <input type="number" step="0.01" min="0.01" max="{{ $c->claimed_total }}" name="approved_total" value="{{ $c->claimed_total }}" class="form-control form-control-sm" style="width:100px">
                                            <button class="btn btn-sm btn-success" onclick="return confirm('Approve and add to the driver\'s earnings?')">{{ translate('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.urban-goodz.order-anywhere.reimbursements.reject', $c->id) }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="text" name="notes" required maxlength="1000" placeholder="{{ translate('Reason (shown to support)') }}" class="form-control form-control-sm">
                                            <button class="btn btn-sm btn-outline-danger">{{ translate('Reject') }}</button>
                                        </form>
                                    @elseif($c->review_notes)
                                        <small class="text-muted">{{ $c->review_notes }}</small>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-4">{{ translate('No claims here') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $claims->links() }}</div>
        </div>
    </div>
@endsection
