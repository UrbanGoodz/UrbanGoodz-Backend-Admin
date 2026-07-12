@extends('layouts.admin.app')

@section('title', translate('Post Details'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Post Details') }}</h1>
            <a href="{{ route('admin.urban-goodz.community.posts') }}" class="btn btn-outline-secondary btn-sm">
                <i class="tio-arrow-left"></i> {{ translate('Back to Posts') }}
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h3>{{ $post->title }}</h3>
                                <small class="text-muted">
                                    {{ translate('By') }} {{ $post->author_name ?? 'Unknown' }}
                                    {{ $post->author_email ? "<{$post->author_email}>" : '' }}
                                    &middot; {{ $post->created_at->format('M d, Y g:i A') }}
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                @if($post->type)
                                <span class="badge badge-soft-info">{{ $post->type }}</span>
                                @endif
                                @if($post->is_published)
                                    <span class="badge badge-soft-success">{{ translate('Published') }}</span>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('Draft') }}</span>
                                @endif
                            </div>
                        </div>
                        <hr>
                        <div class="mt-3">{{ nl2br(e($post->body)) }}</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Comments') }} ({{ $comments->total() }})</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Author') }}</th>
                                        <th>{{ translate('Comment') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Date') }}</th>
                                        <th>{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($comments as $comment)
                                    <tr>
                                        <td>{{ $comment->author_name ?? '-' }}</td>
                                        <td><small>{{ Str::limit($comment->body, 80) }}</small></td>
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
                                        <td colspan="5" class="text-center text-muted py-3">{{ translate('No comments on this post') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-end">
                        {{ $comments->links() }}
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Post Info') }}</h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5">{{ translate('ID') }}</dt>
                            <dd class="col-sm-7">{{ $post->id }}</dd>

                            <dt class="col-sm-5">{{ translate('Type') }}</dt>
                            <dd class="col-sm-7">{{ $post->type ?? '-' }}</dd>

                            <dt class="col-sm-5">{{ translate('Published') }}</dt>
                            <dd class="col-sm-7">
                                @if($post->is_published)
                                    <span class="badge badge-soft-success">{{ translate('Yes') }}</span>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('No') }}</span>
                                @endif
                            </dd>

                            <dt class="col-sm-5">{{ translate('Published At') }}</dt>
                            <dd class="col-sm-7">{{ $post->published_at ? $post->published_at->format('M d, Y g:i A') : '-' }}</dd>

                            <dt class="col-sm-5">{{ translate('Created') }}</dt>
                            <dd class="col-sm-7">{{ $post->created_at->format('M d, Y g:i A') }}</dd>

                            <dt class="col-sm-5">{{ translate('Updated') }}</dt>
                            <dd class="col-sm-7">{{ $post->updated_at->format('M d, Y g:i A') }}</dd>
                        </dl>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.urban-goodz.community.posts.toggle-publish', $post->id) }}">
                            @csrf
                            @if($post->is_published)
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="tio-archive"></i> {{ translate('Unpublish Post') }}
                            </button>
                            @else
                            <button type="submit" class="btn btn-success w-100">
                                <i class="tio-check-circle"></i> {{ translate('Publish Post') }}
                            </button>
                            @endif
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
