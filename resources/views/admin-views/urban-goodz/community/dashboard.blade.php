@extends('layouts.admin.app')

@section('title', translate('Community Dashboard'))

@section('content')
    <div class="content container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <h1 class="page-header-title">{{ translate('Community Dashboard') }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('admin.urban-goodz.community.posts') }}" class="btn btn--primary btn-sm">
                    <i class="tio-list-bulleted"></i> {{ translate('Manage Posts') }}
                </a>
                <a href="{{ route('admin.urban-goodz.community.comments') }}" class="btn btn--primary btn-sm">
                    <i class="tio-chat-bubble"></i> {{ translate('Manage Comments') }}
                </a>
                <a href="{{ route('admin.urban-goodz.community.marketplace') }}" class="btn btn--primary btn-sm">
                    <i class="tio-shopping-cart"></i> {{ translate('Marketplace') }}
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-2 col-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Total Posts') }}</small>
                        <h3 class="mb-0">{{ $stats['posts'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Published') }}</small>
                        <h3 class="mb-0">{{ $stats['published_posts'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Total Comments') }}</small>
                        <h3 class="mb-0">{{ $stats['comments'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Pending Comments') }}</small>
                        <h3 class="mb-0 text-warning">{{ $stats['pending_comments'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Marketplace Items') }}</small>
                        <h3 class="mb-0">{{ $stats['marketplace_items'] }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-4">
                <div class="card">
                    <div class="card-body">
                        <small class="text-muted">{{ translate('Active Listings') }}</small>
                        <h3 class="mb-0 text-success">{{ $stats['active_items'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ translate('Recent Posts') }}</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Title') }}</th>
                                        <th>{{ translate('Author') }}</th>
                                        <th>{{ translate('Status') }}</th>
                                        <th>{{ translate('Date') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentPosts as $post)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.urban-goodz.community.posts.show', $post->id) }}">
                                                {{ Str::limit($post->title, 40) }}
                                            </a>
                                        </td>
                                        <td>{{ $post->author_name ?? '-' }}</td>
                                        <td>
                                            @if($post->is_published)
                                                <span class="badge badge-soft-success">{{ translate('Published') }}</span>
                                            @else
                                                <span class="badge badge-soft-secondary">{{ translate('Draft') }}</span>
                                            @endif
                                        </td>
                                        <td><small>{{ $post->created_at->format('M d, Y') }}</small></td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-3">{{ translate('No posts yet') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ translate('Pending Comments') }}</h5>
                        <a href="{{ route('admin.urban-goodz.community.comments', ['filter' => 'pending']) }}" class="btn btn-sm btn-outline-warning">{{ translate('View All') }}</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ translate('Author') }}</th>
                                        <th>{{ translate('Comment') }}</th>
                                        <th>{{ translate('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($pendingComments as $comment)
                                    <tr>
                                        <td>{{ $comment->author_name ?? '-' }}</td>
                                        <td><small>{{ Str::limit($comment->body, 50) }}</small></td>
                                        <td>
                                            <div class="btn-group">
                                                <form method="POST" action="{{ route('admin.urban-goodz.community.comments.approve', $comment->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-success" title="{{ translate('Approve') }}">
                                                        <i class="tio-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('admin.urban-goodz.community.comments.reject', $comment->id) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="{{ translate('Reject') }}">
                                                        <i class="tio-close"></i>
                                                    </button>
                                                </form>
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
                                        <td colspan="3" class="text-center text-muted py-3">{{ translate('No pending comments') }}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">{{ translate('Recent Marketplace Items') }}</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>{{ translate('Title') }}</th>
                                <th>{{ translate('Seller') }}</th>
                                <th>{{ translate('Price') }}</th>
                                <th>{{ translate('Status') }}</th>
                                <th>{{ translate('Active') }}</th>
                                <th>{{ translate('Date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentMarketplace as $item)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.urban-goodz.community.marketplace.show', $item->id) }}">
                                        {{ Str::limit($item->title, 40) }}
                                    </a>
                                </td>
                                <td>{{ $item->seller_name ?? '-' }}</td>
                                <td><strong>{{ $item->currency ?? '$' }}{{ number_format($item->price, 2) }}</strong></td>
                                <td>
                                    @php $statusColors = ['available' => 'success', 'sold' => 'secondary', 'reserved' => 'warning', 'expired' => 'danger']; @endphp
                                    <span class="badge badge-soft-{{ $statusColors[$item->status] ?? 'secondary' }}">{{ ucfirst($item->status) }}</span>
                                </td>
                                <td>
                                    @if($item->is_active)
                                        <span class="badge badge-soft-success">{{ translate('Active') }}</span>
                                    @else
                                        <span class="badge badge-soft-secondary">{{ translate('Inactive') }}</span>
                                    @endif
                                </td>
                                <td><small>{{ $item->created_at->format('M d, Y') }}</small></td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">{{ translate('No marketplace items yet') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
