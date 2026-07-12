<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzSpotlightBusiness;
use Illuminate\Http\Request;

class UrbanGoodzSpotlightBusinessController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzSpotlightBusiness::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('business_name', 'like', '%' . $request->search . '%')
                    ->orWhere('category', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $businesses = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.spotlight-businesses.index', compact('businesses'));
    }

    public function show($id)
    {
        $business = UrbanGoodzSpotlightBusiness::findOrFail($id);

        return view('admin-views.urban-goodz.spotlight-businesses.show', compact('business'));
    }

    public function create()
    {
        return view('admin-views.urban-goodz.spotlight-businesses.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'featured_until' => ['nullable', 'date'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active', true);

        UrbanGoodzSpotlightBusiness::create($data);

        return redirect()->route('admin.urban-goodz.spotlight-businesses.index')
            ->with('success', translate('Spotlight business created successfully.'));
    }

    public function edit($id)
    {
        $business = UrbanGoodzSpotlightBusiness::findOrFail($id);

        return view('admin-views.urban-goodz.spotlight-businesses.edit', compact('business'));
    }

    public function update(Request $request, $id)
    {
        $business = UrbanGoodzSpotlightBusiness::findOrFail($id);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'vendor_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'featured_until' => ['nullable', 'date'],
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active', true);

        $business->update($data);

        return redirect()->route('admin.urban-goodz.spotlight-businesses.index')
            ->with('success', translate('Spotlight business updated successfully.'));
    }

    public function destroy($id)
    {
        $business = UrbanGoodzSpotlightBusiness::findOrFail($id);
        $business->delete();

        return back()->with('success', translate('Spotlight business deleted successfully.'));
    }

    public function status($id, $status)
    {
        $business = UrbanGoodzSpotlightBusiness::findOrFail($id);
        $business->is_active = $status === '1';
        $business->save();

        return back()->with('success', translate('Spotlight business status updated successfully.'));
    }
}
