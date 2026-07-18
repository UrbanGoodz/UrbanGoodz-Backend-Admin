<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzCreatorApplication;
use App\Models\UrbanGoodzCreatorContent;
use Illuminate\Http\Request;

class CreatorCommerceTesterController extends Controller
{
    public function customerApplications(Request $request)
    {
        [$email, $phone] = $this->identity($request);

        $records = UrbanGoodzCreatorApplication::query()
            ->when($email || $phone, function ($query) use ($email, $phone) {
                $query->where(function ($q) use ($email, $phone) {
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                });
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function featuredReels(Request $request)
    {
        $limit = max(1, min((int) $request->input('limit', 20), 50));

        $records = UrbanGoodzCreatorContent::query()
            ->with(['profile:id,display_name', 'application:id,creator_name'])
            ->where(function ($query) {
                $query->where('is_featured', true)
                    ->orWhere('is_published', true)
                    ->orWhere('status', 'published');
            })
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (UrbanGoodzCreatorContent $content) {
                $creatorName = trim((string) ($content->profile?->display_name ?: $content->application?->creator_name ?: 'Urban Goodz Creator'));

                return [
                    'id' => $content->id,
                    'title' => $content->title,
                    'creator_name' => $creatorName,
                    'product_name' => $content->linked_vendor_name ?: $content->title,
                    'price_label' => $content->cta_label ?: 'See details',
                    'likes' => (int) $content->likes_count,
                    'views' => (int) $content->clicks_count,
                    'featured' => (bool) $content->is_featured,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function storeApplication(Request $request)
    {
        [$email, $phone] = $this->identity($request);

        $data = $request->validate([
            'creator_name' => ['required_without:name', 'nullable', 'string', 'max:255'],
            'name' => ['required_without:creator_name', 'nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:100'],
            'username' => ['nullable', 'string', 'max:255'],
            'follower_count' => ['nullable', 'integer', 'min:0'],
            'bio' => ['nullable', 'string'],
            'bio_pitch' => ['nullable', 'string'],
            'niche' => ['nullable', 'string', 'max:255'],
            'niche_category' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'market' => ['nullable', 'string', 'max:255'],
            'social_links' => ['nullable'],
            'social_link' => ['nullable', 'string', 'max:2000'],
            'content_samples' => ['nullable'],
            'sell_promote' => ['nullable', 'string'],
        ]);

        $socialLinks = $this->normalizeToArray($data['social_links'] ?? null);
        if (!empty($data['social_link'])) {
            $socialLinks[] = $data['social_link'];
        }

        $contentSamples = $this->normalizeToArray($data['content_samples'] ?? null);
        if (!empty($data['sell_promote'])) {
            $contentSamples[] = $data['sell_promote'];
        }

        $application = UrbanGoodzCreatorApplication::create([
            'creator_name' => $data['creator_name'] ?? $data['name'],
            'email' => $data['email'] ?? $email,
            'phone' => $data['phone'] ?? $phone,
            'platform' => $data['platform'] ?? 'customer_app',
            'username' => $data['username'] ?? null,
            'follower_count' => $data['follower_count'] ?? 0,
            'bio' => $data['bio'] ?? $data['bio_pitch'] ?? null,
            'status' => 'pending',
            'admin_notes' => null,
            'niche' => $data['niche'] ?? $data['niche_category'] ?? null,
            'city' => $data['city'] ?? null,
            'market' => $data['market'] ?? null,
            'social_links' => $socialLinks ?: null,
            'content_samples' => $contentSamples ?: null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Creator application submitted for admin review.',
            'data' => $application,
        ], 201);
    }

    public function storePromotion(Request $request)
    {
        [$email, $phone] = $this->identity($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'promotion_type' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'string'],
            'creator_application_id' => ['nullable', 'integer', 'exists:urban_goodz_creator_applications,id'],
        ]);

        $applicationId = $data['creator_application_id'] ?? UrbanGoodzCreatorApplication::query()
            ->when($email || $phone, function ($query) use ($email, $phone) {
                $query->where(function ($q) use ($email, $phone) {
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                });
            })
            ->latest('id')
            ->value('id');

        $promotion = UrbanGoodzCreatorContent::create([
            'creator_application_id' => $applicationId,
            'title' => $data['title'],
            'description' => $data['details'] ?? null,
            'content_type' => 'promotion',
            'linked_vendor_type' => $data['promotion_type'] ?? null,
            'status' => 'submitted',
            'is_published' => false,
            'is_featured' => false,
            'admin_notes' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Creator promotion submitted for admin review.',
            'data' => $promotion,
        ], 201);
    }

    public function promotions(Request $request)
    {
        [$email, $phone] = $this->identity($request);

        $records = UrbanGoodzCreatorContent::query()
            ->where('content_type', 'promotion')
            ->when($email || $phone, function ($query) use ($email, $phone) {
                $query->whereHas('application', function ($q) use ($email, $phone) {
                    if ($email) {
                        $q->orWhere('email', $email);
                    }
                    if ($phone) {
                        $q->orWhere('phone', $phone);
                    }
                });
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function adminApplications(Request $request)
    {
        $records = UrbanGoodzCreatorApplication::query()->latest()->paginate(25);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function updateApplicationStatus(Request $request, $record)
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $application = UrbanGoodzCreatorApplication::query()->findOrFail($record);
        $application->status = $data['status'] ?? 'under_review';
        $application->admin_notes = $data['admin_notes'] ?? $application->admin_notes;
        $application->save();

        return response()->json(['success' => true, 'data' => $application]);
    }

    public function updatePromotionStatus(Request $request, $record)
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $promotion = UrbanGoodzCreatorContent::query()->where('content_type', 'promotion')->findOrFail($record);
        $promotion->status = $data['status'] ?? 'under_review';
        $promotion->admin_notes = $data['admin_notes'] ?? $promotion->admin_notes;
        $promotion->save();

        return response()->json(['success' => true, 'data' => $promotion]);
    }

    private function identity(Request $request): array
    {
        $user = $request->user();

        return [
            $user?->email,
            $user?->phone,
        ];
    }

    private function normalizeToArray(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($item) => $item !== null && $item !== ''));
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, fn ($item) => $item !== null && $item !== ''));
            }

            return [$value];
        }

        return [];
    }
}
