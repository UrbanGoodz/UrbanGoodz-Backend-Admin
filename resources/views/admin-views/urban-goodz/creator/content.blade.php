@extends('layouts.admin.app')

@section('title', translate('Creator Content'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header"><h1>{{ translate('Creator Content') }}</h1></div>

        <div class="card">
            <div class="card-header">
                <form class="row g-2" method="GET">
                    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="{{ translate('Search by title') }}" value="{{ request('search') }}"></div>
                    <div class="col-md-2">
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Statuses') }}</option>
                            <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>{{ translate('Draft') }}</option>
                            <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>{{ translate('Submitted') }}</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                            <option value="published" {{ request('status') == 'published' ? 'selected' : '' }}>{{ translate('Published') }}</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ translate('Rejected') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="content_type" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All Types') }}</option>
                            <option value="video" {{ request('content_type') == 'video' ? 'selected' : '' }}>{{ translate('Video') }}</option>
                            <option value="image" {{ request('content_type') == 'image' ? 'selected' : '' }}>{{ translate('Image') }}</option>
                            <option value="carousel" {{ request('content_type') == 'carousel' ? 'selected' : '' }}>{{ translate('Carousel') }}</option>
                            <option value="reel" {{ request('content_type') == 'reel' ? 'selected' : '' }}>{{ translate('Reel') }}</option>
                            <option value="lookbook" {{ request('content_type') == 'lookbook' ? 'selected' : '' }}>{{ translate('Lookbook') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="is_shoppable" class="form-control" onchange="this.form.submit()">
                            <option value="">{{ translate('All') }}</option>
                            <option value="1" {{ request('is_shoppable') == '1' ? 'selected' : '' }}>{{ translate('Shoppable') }}</option>
                            <option value="0" {{ request('is_shoppable') == '0' ? 'selected' : '' }}>{{ translate('Not Shoppable') }}</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary"><i class="tio-filter"></i></button></div>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover m-0">
                    <thead><tr><th>{{ translate('Title') }}</th><th>{{ translate('Creator') }}</th><th>{{ translate('Type') }}</th><th>{{ translate('Clicks') }}</th><th>{{ translate('Shoppable') }}</th><th>{{ translate('Status') }}</th><th>{{ translate('Date') }}</th><th></th></tr></thead>
                    <tbody>
                    @forelse($content as $item)
                        <tr>
                            <td>{{ str_limit($item->title, 45) }}</td>
                            <td>{{ $item->profile->display_name ?? 'N/A' }}</td>
                            <td>{{ $item->content_type }}</td>
                            <td>{{ number_format($item->clicks_count) }}</td>
                            <td>{!! $item->is_shoppable ? '<span class="badge badge-success">'.translate('Yes').'</span>' : '<span class="badge badge-secondary">'.translate('No').'</span>' !!}</td>
                            <td><span class="badge badge-{{ $item->status == 'published' ? 'success' : ($item->status == 'rejected' ? 'danger' : ($item->status == 'approved' ? 'info' : 'secondary')) }}">{{ $item->status }}</span></td>
                            <td>{{ $item->created_at->format('M d, Y') }}</td>
                            <td><a href="{{ route('admin.urban-goodz.creator.content.show', $item->id) }}" class="btn btn-sm btn-ghost-info"><i class="tio-visible"></i></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center">{{ translate('No content found') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if($content->hasPages())
                <div class="card-footer">{{ $content->links() }}</div>
            @endif
        </div>
    </div>
@endsection
