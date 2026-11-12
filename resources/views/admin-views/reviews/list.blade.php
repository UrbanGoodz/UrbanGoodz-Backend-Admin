@extends('layouts.admin.app')

@section('title', translate('Reviews'))

@push('css_or_js')
@endpush

@section('content')
    <div class="content container-fluid overflow-hidden">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-star"></i>
                </span>
                <span>{{ translate('Reviews') }}</span>
                <span class="badge badge-soft-dark ms-2">{{ $reviews->total() }}</span>
            </h1>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <div class="search-form">
                    {{-- Posts to ReviewsController@search, which returns the rendered
                         partial below, so the table body is swapped without a reload. --}}
                    <form id="review-search-form" class="d-flex" action="javascript:">
                        @csrf
                        <input type="search" name="search" class="form-control"
                               placeholder="{{ translate('Search by item name') }}" aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--primary ms-2">{{ translate('Search') }}</button>
                    </form>
                </div>
            </div>

            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('SL') }}</th>
                            <th>{{ translate('Item') }}</th>
                            <th>{{ translate('Customer') }}</th>
                            <th>{{ translate('Rating') }}</th>
                            <th>{{ translate('Review') }}</th>
                            <th>{{ translate('Date') }}</th>
                        </tr>
                    </thead>
                    <tbody id="review-table-body">
                        @include('admin-views.reviews.partials._table', ['reviews' => $reviews])
                    </tbody>
                </table>
            </div>

            @if (count($reviews) === 0)
                <div class="empty--data text-center py-5">
                    <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="{{ translate('no_data') }}">
                    <h5>{{ translate('no_data_found') }}</h5>
                </div>
            @endif

            <div class="page-area px-4 pb-3">
                {!! $reviews->links() !!}
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        $('#review-search-form').on('submit', function () {
            let term = $('input[name="search"]').val();

            $.ajax({
                url: '{{ route('admin.reviews.search') }}',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    search: term
                },
                beforeSend: function () {
                    $('#loading').show();
                },
                success: function (data) {
                    $('#review-table-body').html(data.view);
                },
                complete: function () {
                    $('#loading').hide();
                }
            });
        });
    </script>
@endpush
