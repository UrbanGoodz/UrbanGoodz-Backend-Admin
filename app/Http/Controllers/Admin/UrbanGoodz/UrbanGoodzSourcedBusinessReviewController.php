<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\UrbanGoodzSourcedBusiness;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UrbanGoodzSourcedBusinessReviewController extends Controller
{
    // Default batch marker for the P3B staging import.
    private const DEFAULT_MARKER = 'urban_goodz_session1_p3_staging_import_20260709_001';

    private const REVIEW_STATUSES = ['pending', 'approved', 'rejected', 'merge_required'];

    public function index(Request $request)
    {
        $marker = $request->input('batch', self::DEFAULT_MARKER);
        $rows = UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->get();

        // Apply optional filters (computed in PHP; the set is small ~431 rows).
        if ($request->filled('review_status')) {
            $rows = $rows->where('admin_review_status', $request->input('review_status'));
        }
        if ($request->filled('module_id')) {
            $rows = $rows->where('module_id', (int) $request->input('module_id'));
        }
        if ($request->filled('city')) {
            $rows = $rows->where('city', $request->input('city'));
        }
        if ($request->filled('state')) {
            $rows = $rows->where('state', $request->input('state'));
        }
        if ($request->input('age_restricted') === '1') {
            $rows = $rows->filter(fn ($r) => $r->fulfillment_modes === ['review_only']);
        }
        if ($request->input('category_pending') === '1') {
            $rows = $rows->filter(fn ($r) => empty($r->category_ids));
        }
        if ($request->input('source_url_invalid') === '1') {
            $rows = $rows->filter(fn ($r) => !$this->isValidUrl((string) ($r->source_urls[0] ?? '')));
        }

        $stats = [
            'total' => UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->count(),
            'pending' => UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status', 'pending')->count(),
            'approved' => UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status', 'approved')->count(),
            'rejected' => UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->where('admin_review_status', 'rejected')->count(),
            'category_pending' => UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->get()->filter(fn ($r) => empty($r->category_ids))->count(),
            'age_review_only' => UrbanGoodzSourcedBusiness::where('created_by_source', $marker)->get()->filter(fn ($r) => $r->fulfillment_modes === ['review_only'])->count(),
        ];

        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $total = $rows->count();
        $items = $rows->forPage($page, $perPage)->values();
        $rows = new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => $request->url(),
            'query' => $request->query(),
        ]);

        $modules = DB::table('modules')->orderBy('module_name')->get(['id', 'module_name']);

        return view('admin-views.urban-goodz.sourced-businesses.index', compact(
            'rows', 'stats', 'marker', 'modules', 'page', 'perPage'
        ));
    }

    public function show($id)
    {
        $business = UrbanGoodzSourcedBusiness::findOrFail($id);
        $categories = Category::where('module_id', $business->module_id)
            ->orderBy('name')
            ->get(['id', 'module_id', 'name']);

        return view('admin-views.urban-goodz.sourced-businesses.show', compact('business', 'categories'));
    }

    public function update(Request $request, $id)
    {
        $business = UrbanGoodzSourcedBusiness::findOrFail($id);

        $data = $request->validate([
            'admin_review_status' => ['required', 'string', 'in:' . implode(',', self::REVIEW_STATUSES)],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'min:1'],
        ]);

        // Safety lock: never allow the [1] (Demo category) fallback / fake id.
        $categoryIds = array_map('intval', (array) ($data['category_ids'] ?? []));
        $categoryIds = array_values(array_unique(array_filter($categoryIds, fn ($id) => $id > 1)));
        if (!empty($categoryIds)) {
            $allowed = Category::where('module_id', $business->module_id)
                ->whereIn('id', $categoryIds)
                ->pluck('id')
                ->all();
            $categoryIds = array_values(array_intersect($categoryIds, $allowed));
        }

        $business->update([
            'admin_review_status' => $data['admin_review_status'],
            'category_ids' => $categoryIds,
        ]);

        Toastr::success('Sourced business review saved. No store, vendor, or product was created.');

        return redirect()->route('admin.urban-goodz.sourced-businesses.show', $business->id);
    }

    private function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }
}
