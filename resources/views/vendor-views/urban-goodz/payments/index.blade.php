@extends('layouts.vendor.app')

@section('title', translate('Urban Goodz Earnings'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">{{ translate('Urban Goodz Earnings') }}</h1>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                    <tr>
                        <th>{{ translate('Feature') }}</th>
                        <th>{{ translate('Type') }}</th>
                        <th>{{ translate('Amount') }}</th>
                        <th>{{ translate('Status') }}</th>
                        <th>{{ translate('Created') }}</th>
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
                        <tr><td colspan="5" class="text-center">{{ translate('No Urban Goodz earnings found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer">{{ $splits->links() }}</div>
        </div>
    </div>
@endsection
