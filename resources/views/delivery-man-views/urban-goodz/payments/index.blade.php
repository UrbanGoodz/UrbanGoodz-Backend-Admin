@extends('delivery-man-views.urban-goodz.layout')

@section('title', 'Urban Goodz Earnings')

@section('content')
    <div class="page-header">
        <h1 class="page-header-title">Urban Goodz Earnings</h1>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>Feature</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
                </thead>
                <tbody>
                @forelse($splits as $split)
                    <tr>
                        <td>{{ $split->feature }}</td>
                        <td>{{ $split->split_type }}</td>
                        <td>{{ $split->currency }} {{ $split->amount }}</td>
                        <td>{{ $split->status }}</td>
                        <td>{{ optional($split->created_at)->format('Y-m-d H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center">No Urban Goodz earnings found</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $splits->links() }}</div>
    </div>
@endsection
