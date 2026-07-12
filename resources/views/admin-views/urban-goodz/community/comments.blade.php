@extends('layouts.admin.app')

@section('title', translate('Community Comments'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Community Comments') }}</h1>
            <a href="{{ route('admin.urban-goodz.community.dashboard') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-arrow-left"></i> {{ translate('Dashboard') }}
            </a>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label form-label-sm">{{ translate('Filter') }}</label>
                        <select name="filter" class="form-control form-control-sm">
                            <option value="">{{ translate('All') }}</option>
                            <option value="pending" {{ request('filter') === 'pending' ? 'selected' : '' }}>{{ translate('Pending') }}</option>
                            <option value="approved" {{ request('filter') === 'approved' ? 'selected' : '' }}>{{ translate('Approved') }}</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label form-label-sm">{{ translate('Search') }}</label>
                        <input type="text" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="{{ translate('Search by author or body...') }}">
                    </div>
                    <div class="col-md-3 d-flex gap-1">
                        <button type="submit" class="btn btn-sm btn--primary flex-grow-1"><i class="tio-search"></i> {{ translate('Search') }}</button>
                        @if(request('filter') || request('q'))
                        <a href="{{ route('admin.urban-goodz.community.comments') }}" class="btn btn-sm btn-outline-secondary">{{ translate('Reset') }}</a>
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
                                <th>{{ translate('Author') }}</th>
                                <th>{{ translate('Comment') }}</th>
                                <th>{{ translate('Post ID') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Date') }}</th>
                                <th>{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($comments as $comment)
                            <tr>
                                <td>{{ $comment->id }}</td>
                                <td>{{ $comment->author_name ?? '-' }}</td>
                                <td><small>{{ Str::limit($comment->body, 60) }}</small></td>
                                <td>
                                    @if($comment->post_id)
                                    <a href="{{ route('admin.urban-goodz.community.posts.show', $comment->post_id) }}">{{ $comment->post_id }}</a>
                                    @endif
                                </td>
                                <td>
                                    @if($comment->is_approved)
                                        <span class="badge badge-soft-success">{{ translate('Approved') }}</span>
                                    @else
                                        <span class="badge badge-soft-warning">{{ translate('Pending') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $comment->created_at->format('M d, Y') }}</small></td>
                                <td>
                                    <div class="btn-group">
                                        @if(!$comment->is_approved)
                                        <form method="POST" action="{{ route('admin.urban-goodz.community.comments.approve', $comment->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="{{ translate('Approve') }}">
                                                <i class="tio-check"></i>
                                            </button>
                                        </form>
                                        @else
                                        <form method="POST" action="{{ route('admin.urban-goodz.community.comments.reject', $comment->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ translate('Reject') }}">
                                                <i class="tio-close"></i>
                                            </button>
                                        </form>
                                        @endif
                                        <form method="POST" action="{{ route('admin.urban-goodz.community.comments.destroy', $comment->id) }}" class="d-inline" onsubmit="return confirm('{{ translate('Delete this comment?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ translate('Delete') }}">
                                                <i class="tio-delete"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ translate('No comments found') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                {{ $comments->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection
