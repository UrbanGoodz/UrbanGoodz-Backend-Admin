<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzCommunityPost;
use App\Models\UrbanGoodzCommunityComment;
use App\Models\UrbanGoodzCommunityMarketplaceItem;
use Illuminate\Http\Request;

class UrbanGoodzCommunityController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'posts' => UrbanGoodzCommunityPost::count(),
            'published_posts' => UrbanGoodzCommunityPost::where('is_published', true)->count(),
            'comments' => UrbanGoodzCommunityComment::count(),
            'pending_comments' => UrbanGoodzCommunityComment::where('is_approved', false)->count(),
            'marketplace_items' => UrbanGoodzCommunityMarketplaceItem::count(),
            'active_items' => UrbanGoodzCommunityMarketplaceItem::where('is_active', true)->count(),
        ];

        $recentPosts = UrbanGoodzCommunityPost::latest()->limit(10)->get();
        $pendingComments = UrbanGoodzCommunityComment::where('is_approved', false)->latest()->limit(10)->get();
        $recentMarketplace = UrbanGoodzCommunityMarketplaceItem::latest()->limit(10)->get();

        return view('admin-views.urban-goodz.community.dashboard', compact('stats', 'recentPosts', 'pendingComments', 'recentMarketplace'));
    }

    public function posts(Request $request)
    {
        $query = UrbanGoodzCommunityPost::query();

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('author_name', 'like', "%{$q}%")
                    ->orWhere('type', 'like', "%{$q}%");
            });
        }

        $posts = $query->latest()->paginate(15)->withQueryString();

        return view('admin-views.urban-goodz.community.posts', compact('posts'));
    }

    public function postShow($id)
    {
        $post = UrbanGoodzCommunityPost::findOrFail($id);
        $comments = UrbanGoodzCommunityComment::where('post_id', $id)->latest()->paginate(20);

        return view('admin-views.urban-goodz.community.post-show', compact('post', 'comments'));
    }

    public function postTogglePublish($id)
    {
        $post = UrbanGoodzCommunityPost::findOrFail($id);
        $post->is_published = !$post->is_published;
        if ($post->is_published && !$post->published_at) {
            $post->published_at = now();
        }
        $post->save();

        return redirect()->back()->with('success', 'Post publish status updated.');
    }

    public function comments(Request $request)
    {
        $query = UrbanGoodzCommunityComment::query();

        if ($request->filled('filter')) {
            if ($request->input('filter') === 'pending') {
                $query->where('is_approved', false);
            } elseif ($request->input('filter') === 'approved') {
                $query->where('is_approved', true);
            }
        }

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('author_name', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            });
        }

        $comments = $query->latest()->paginate(15)->withQueryString();

        return view('admin-views.urban-goodz.community.comments', compact('comments'));
    }

    public function commentApprove($id)
    {
        $comment = UrbanGoodzCommunityComment::findOrFail($id);
        $comment->is_approved = true;
        $comment->save();

        return redirect()->back()->with('success', 'Comment approved.');
    }

    public function commentReject($id)
    {
        $comment = UrbanGoodzCommunityComment::findOrFail($id);
        $comment->is_approved = false;
        $comment->save();

        return redirect()->back()->with('success', 'Comment rejected.');
    }

    public function commentDestroy($id)
    {
        UrbanGoodzCommunityComment::findOrFail($id)->delete();

        return redirect()->back()->with('success', 'Comment deleted.');
    }

    public function marketplace(Request $request)
    {
        $query = UrbanGoodzCommunityMarketplaceItem::query();

        if ($q = $request->input('q')) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('seller_name', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->input('active') === '1');
        }

        $items = $query->latest()->paginate(15)->withQueryString();

        return view('admin-views.urban-goodz.community.marketplace', compact('items'));
    }

    public function marketplaceShow($id)
    {
        $item = UrbanGoodzCommunityMarketplaceItem::findOrFail($id);

        return view('admin-views.urban-goodz.community.marketplace-show', compact('item'));
    }

    public function marketplaceToggleActive($id)
    {
        $item = UrbanGoodzCommunityMarketplaceItem::findOrFail($id);
        $item->is_active = !$item->is_active;
        $item->save();

        return redirect()->back()->with('success', 'Marketplace item active status updated.');
    }

    public function marketplaceUpdateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:available,sold,reserved,expired',
        ]);

        $item = UrbanGoodzCommunityMarketplaceItem::findOrFail($id);
        $item->status = $request->input('status');
        $item->save();

        return redirect()->back()->with('success', 'Marketplace item status updated.');
    }
}
