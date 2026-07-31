<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzSourcedBusiness;
use Illuminate\Http\Request;

class UrbanGoodzMarketplaceDataController extends Controller
{
    public function businesses(Request $request)
    {
        $businesses = UrbanGoodzSourcedBusiness::query()
            ->apiVisible()
            ->with([
                'products' => fn ($query) => $query->apiVisible(),
                'products.sourcedImages' => fn ($query) => $query->where('api_visible', true)->where('review_status', 'approved'),
                'images' => fn ($query) => $query->where('api_visible', true)->where('review_status', 'approved'),
            ])
            ->when($request->filled('city'), fn ($query) => $query->where('city', $request->input('city')))
            ->when($request->filled('state'), fn ($query) => $query->where('state', $request->input('state')))
            ->when($request->filled('module_id'), fn ($query) => $query->where('module_id', $request->integer('module_id')))
            ->latest('id')
            ->paginate(min(50, max(1, $request->integer('per_page', 25))));

        return response()->json(['success' => true, 'data' => $businesses]);
    }

    public function business(int $id)
    {
        $business = UrbanGoodzSourcedBusiness::query()
            ->apiVisible()
            ->with([
                'products' => fn ($query) => $query->apiVisible(),
                'products.sourcedImages' => fn ($query) => $query->where('api_visible', true)->where('review_status', 'approved'),
                'images' => fn ($query) => $query->where('api_visible', true)->where('review_status', 'approved'),
            ])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $business]);
    }

    public function shopperCatalogs(Request $request)
    {
        $businesses = UrbanGoodzSourcedBusiness::query()
            ->shopperVisible()
            ->with([
                'products' => fn ($query) => $query->shopperVisible(),
                'products.sourcedImages' => fn ($query) => $query->where('shopper_visible', true)->where('review_status', 'approved'),
                'images' => fn ($query) => $query->where('shopper_visible', true)->where('review_status', 'approved'),
            ])
            ->when($request->filled('city'), fn ($query) => $query->where('city', $request->input('city')))
            ->when($request->filled('module_id'), fn ($query) => $query->where('module_id', $request->integer('module_id')))
            ->latest('id')
            ->paginate(min(50, max(1, $request->integer('per_page', 25))));

        return response()->json(['success' => true, 'data' => $businesses]);
    }
}
