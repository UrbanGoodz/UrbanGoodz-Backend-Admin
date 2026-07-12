@extends('layouts.admin.app')

@section('title', translate('AI Concierge - Conversations'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-sm-0">
                    <h1 class="page-header-title">
                        <span>{{ translate('AI Concierge - Conversations') }}</span>
                        <span class="badge badge-soft-dark ml-2">{{ $conversations->total() }}</span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a href="{{ route('admin.urban-goodz.index') }}" class="btn btn--secondary">{{ translate('Back') }}</a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>#</th>
                            <th>{{ translate('User') }}</th>
                            <th>{{ translate('Detected Intent') }}</th>
                            <th>{{ translate('Status') }}</th>
                            <th>{{ translate('Messages') }}</th>
                            <th>{{ translate('Date') }}</th>
                            <th>{{ translate('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($conversations as $key => $c)
                            <tr>
                                <td>{{ $conversations->firstItem() + $key }}</td>
                                <td>{{ $c->customer_id ?? translate('Guest') }}</td>
                                <td>{{ $c->detectedIntent->name ?? translate('Unknown') }}</td>
                                <td>
                                    <span class="badge badge-soft-{{ $c->status === 'resolved' ? 'success' : ($c->status === 'escalated' ? 'danger' : 'info') }}">
                                        {{ $c->status }}
                                    </span>
                                </td>
                                <td>{{ mb_substr($c->query_text ?? '', 0, 50) . (mb_strlen($c->query_text ?? '') > 50 ? '...' : '') }}</td>
                                <td>{{ $c->created_at->format('M d, Y H:i') }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.ai-concierge.conversations.show', $c->id) }}" class="btn btn-sm btn--primary">
                                        {{ translate('View') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        @if(count($conversations) === 0)
                            <tr>
                                <td colspan="7" class="text-center">{{ translate('No conversations found') }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                {{ $conversations->links() }}
            </div>
        </div>
    </div>
@endsection
