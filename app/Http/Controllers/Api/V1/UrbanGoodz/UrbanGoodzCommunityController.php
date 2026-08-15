<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzCommunityComment;
use App\Models\UrbanGoodzCommunityMarketplaceItem;
use App\Models\UrbanGoodzCommunityPost;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Groups are not a stored table -- they are zones (real delivery zones this
 * platform already operates in) plus two synthetic scopes, Nationwide and
 * Worldwide. Member counts are the number of distinct people who have
 * actually posted or commented in that scope; there is no membership/join
 * system, so this is real activity, not an invented number.
 */
class UrbanGoodzCommunityController extends Controller
{
    public function groups(Request $request): JsonResponse
    {
        $zones = Zone::query()->where('status', 1)->orderBy('name')->get(['id', 'name']);

        $groups = $zones->map(function (Zone $zone) {
            return $this->groupPayload(
                key: 'zone:' . $zone->id,
                name: $zone->name ?: 'Local Zone',
                zoneId: $zone->id,
                nationwide: false,
                worldwide: false,
            );
        })->values();

        $groups->push($this->groupPayload('nationwide', 'Nationwide Small Business', null, true, false));
        $groups->push($this->groupPayload('worldwide', 'Worldwide Creator Network', null, false, true));

        return response()->json([
            'status' => 'success',
            'groups' => $groups,
        ]);
    }

    private function groupPayload(string $key, string $name, ?int $zoneId, bool $nationwide, bool $worldwide): array
    {
        $postQuery = UrbanGoodzCommunityPost::query()->forGroup($zoneId, $nationwide, $worldwide);
        $itemQuery = UrbanGoodzCommunityMarketplaceItem::query();
        if ($worldwide) {
            $itemQuery->whereRaw('1 = 0'); // marketplace items are not modelled worldwide
        } elseif ($nationwide) {
            $itemQuery->where('is_nationwide', true);
        } else {
            $itemQuery->where('zone_id', $zoneId);
        }

        $memberCount = $postQuery->clone()->whereNotNull('author_email')->distinct()->count('author_email');

        return [
            'id' => $key,
            'zone_id' => $zoneId,
            'zone_name' => $name,
            'group_name' => $name,
            'category' => $worldwide ? 'Worldwide' : ($nationwide ? 'Nationwide' : 'Local'),
            'is_nationwide' => $nationwide,
            'is_worldwide' => $worldwide,
            'member_count' => $memberCount,
            'post_count' => $postQuery->clone()->where('is_published', true)->count(),
            'marketplace_item_count' => $itemQuery->count(),
        ];
    }

    public function posts(Request $request): JsonResponse
    {
        [$zoneId, $nationwide, $worldwide, $error] = $this->resolveScope($request);
        if ($error) {
            return response()->json(['status' => 'error', 'message' => $error], 422);
        }

        $posts = UrbanGoodzCommunityPost::query()
            ->forGroup($zoneId, $nationwide, $worldwide)
            ->where('is_published', true)
            ->withCount(['comments' => fn ($q) => $q->where('is_approved', true)])
            ->orderByDesc('published_at')
            ->paginate((int) $request->query('limit', 20));

        return response()->json([
            'status' => 'success',
            'posts' => $posts->items(),
            'has_more' => $posts->hasMorePages(),
        ]);
    }

    public function showPost(Request $request, int $post): JsonResponse
    {
        $model = UrbanGoodzCommunityPost::query()->where('is_published', true)->find($post);
        if (!$model) {
            return response()->json(['status' => 'error', 'message' => 'Post not found.'], 404);
        }

        $comments = $model->comments()->where('is_approved', true)->orderBy('created_at')->get();

        return response()->json([
            'status' => 'success',
            'post' => $model,
            'comments' => $comments,
        ]);
    }

    public function storePost(Request $request): JsonResponse
    {
        [$zoneId, $nationwide, $worldwide, $scopeError] = $this->resolveScope($request);
        if ($scopeError) {
            return response()->json(['status' => 'error', 'message' => $scopeError], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:5000',
            'type' => 'nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $post = UrbanGoodzCommunityPost::create([
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'type' => $request->input('type', 'general'),
            'author_name' => $user?->full_name ?: 'Community Member',
            'author_email' => $user?->email,
            'user_id' => $user?->id,
            'zone_id' => $zoneId,
            'is_nationwide' => $nationwide,
            'is_worldwide' => $worldwide,
            'is_published' => true,
            'published_at' => now(),
        ]);

        return response()->json(['status' => 'success', 'post' => $post], 201);
    }

    public function storeComment(Request $request, int $post): JsonResponse
    {
        $model = UrbanGoodzCommunityPost::query()->where('is_published', true)->find($post);
        if (!$model) {
            return response()->json(['status' => 'error', 'message' => 'Post not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'body' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $comment = $model->comments()->create([
            'author_name' => $user?->full_name ?: 'Community Member',
            'body' => $request->input('body'),
            'user_id' => $user?->id,
            'is_approved' => true,
        ]);

        return response()->json(['status' => 'success', 'comment' => $comment], 201);
    }

    public function marketplaceItems(Request $request): JsonResponse
    {
        [$zoneId, $nationwide, , $error] = $this->resolveScope($request, allowWorldwide: false);
        if ($error) {
            return response()->json(['status' => 'error', 'message' => $error], 422);
        }

        $items = UrbanGoodzCommunityMarketplaceItem::query()
            ->forGroup($zoneId, $nationwide)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('limit', 20));

        return response()->json([
            'status' => 'success',
            'items' => $items->items(),
            'has_more' => $items->hasMorePages(),
        ]);
    }

    public function storeMarketplaceItem(Request $request): JsonResponse
    {
        [$zoneId, $nationwide, , $scopeError] = $this->resolveScope($request, allowWorldwide: false);
        if ($scopeError) {
            return response()->json(['status' => 'error', 'message' => $scopeError], 422);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:180',
            'description' => 'nullable|string|max:2000',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'condition' => 'nullable|string|max:40',
            'location' => 'nullable|string|max:180',
            'image_url' => 'nullable|url|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $user = $request->user();

        $item = UrbanGoodzCommunityMarketplaceItem::create([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'currency' => $request->input('currency', 'USD'),
            'condition' => $request->input('condition'),
            'seller_name' => $user?->full_name ?: 'Community Member',
            'seller_contact' => $user?->email,
            'location' => $request->input('location'),
            'image_url' => $request->input('image_url'),
            'status' => 'available',
            'is_active' => true,
            'user_id' => $user?->id,
            'zone_id' => $zoneId,
            'is_nationwide' => $nationwide,
        ]);

        return response()->json(['status' => 'success', 'item' => $item], 201);
    }

    /**
     * @return array{0: ?int, 1: bool, 2: bool, 3: ?string}
     */
    private function resolveScope(Request $request, bool $allowWorldwide = true): array
    {
        $scope = $request->query('scope', 'zone');

        if ($scope === 'worldwide') {
            if (!$allowWorldwide) {
                return [null, false, false, 'This scope does not support worldwide.'];
            }
            return [null, false, true, null];
        }

        if ($scope === 'nationwide') {
            return [null, true, false, null];
        }

        $zoneId = $request->query('zone_id');
        if (!$zoneId || !Zone::query()->where('id', $zoneId)->where('status', 1)->exists()) {
            return [null, false, false, 'A valid zone_id is required for scope=zone.'];
        }

        return [(int) $zoneId, false, false, null];
    }
}
