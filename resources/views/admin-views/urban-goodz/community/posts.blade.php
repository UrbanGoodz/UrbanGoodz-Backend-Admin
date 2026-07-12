@extends('layouts.admin.app')

@section('title', translate('Community Posts'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Community Posts') }}</h1>
            <a href="{{ route('admin.urban-goodz.community.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-arrow-left"></i> {{ translate('Dashboard') }}
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label form-label-sm">{{ translate('Search') }}</label>
                        <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="{{ translate('Search by title, author, or type...') }}">
                    </div>
                    <div class="col-md-4 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn--primary flex-grow-1"><i class="tio-search"></i> {{ translate('Search') }}</button>
                        @if(request('q'))
                        <a href="{{ route('admin.urban-goodz.community.posts') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Author') }}</th>
                                <th>{{ translate('Type') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Published At') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($posts as $post)
                            <tr>
                                <td>{{ $post->id }}</td>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.community.posts.show', $post->id) }}">
                                        <strong>{{ Str::limit($post->title, 50) }}</strong>
                                    </a>
                                </td>
                                <td>{{ $post->author_name ?? '-' }}</td>
                                <td>
                                    @if($post->type)
                                    <span class="badge badge-soft-info">{{ $post->type }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($post->is_published)
                                        <span class="badge badge-soft-success">{{ translate('Published') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('Draft') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $post->published_at ? $post->published_at->format('M d, Y') : '-' }}</small></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.urban-goodz.community.posts.show', $post->id) }}" class="btn btn-sm btn-outline--primary" title="{{ translate('View') }}">
                                            <i class="tio-visible"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.urban-goodz.community.posts.toggle-publish', $post->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-{{ $post->is_published ? 'warning' : 'success' }}" title="{{ $post->is_published ? translate('Unpublish') : translate('Publish') }}">
                                                <i class="tio-{{ $post->is_published ? 'archive' : 'check-circle' }}"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No posts found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $posts->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
