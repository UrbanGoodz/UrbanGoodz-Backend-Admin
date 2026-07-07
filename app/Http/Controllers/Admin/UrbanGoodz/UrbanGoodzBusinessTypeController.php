<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessType;
use App\Models\UrbanGoodzCapability;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzBusinessTypeController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzBusinessType::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('slug', 'like', '%' . $request->search . '%');
            });
        }

        $types = $query->orderBy('sort_order')->orderBy('name')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.business-types.index', compact('types'));
    }

    public function create()
    {
        return view('admin-views.urban-goodz.business-types.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('urban_goodz_business_types', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        UrbanGoodzBusinessType::create($data);

        return redirect()->route('admin.urban-goodz.business-types.index')
            ->with('success', translate('Business type created successfully.'));
    }

    public function edit($id)
    {
        $type = UrbanGoodzBusinessType::findOrFail($id);
        return view('admin-views.urban-goodz.business-types.edit', compact('type'));
    }

    public function update(Request $request, $id)
    {
        $type = UrbanGoodzBusinessType::findOrFail($id);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('urban_goodz_business_types', 'slug')->ignore($type->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $type->update($data);

        return redirect()->route('admin.urban-goodz.business-types.index')
            ->with('success', translate('Business type updated successfully.'));
    }

    public function destroy($id)
    {
        $type = UrbanGoodzBusinessType::findOrFail($id);
        $type->capabilities()->detach();
        $type->delete();

        return back()->with('success', translate('Business type deleted successfully.'));
    }

    public function status($id, $status)
    {
        $type = UrbanGoodzBusinessType::findOrFail($id);
        $type->is_active = $status === '1';
        $type->save();

        return back()->with('success', translate('Business type status updated successfully.'));
    }

    public function mapping($id)
    {
        $type = UrbanGoodzBusinessType::with('capabilities')->findOrFail($id);
        $capabilities = UrbanGoodzCapability::orderBy('group')->orderBy('sort_order')->get();
        $assignedIds = $type->capabilities->pluck('id')->toArray();

        return view('admin-views.urban-goodz.business-types.mapping', compact('type', 'capabilities', 'assignedIds'));
    }

    public function mappingUpdate(Request $request, $id)
    {
        $type = UrbanGoodzBusinessType::findOrFail($id);

        $data = $request->validate([
            'capabilities' => ['nullable', 'array'],
            'capabilities.*' => ['integer', 'exists:urban_goodz_capabilities,id'],
            'is_required' => ['nullable', 'array'],
            'is_required.*' => ['boolean'],
        ]);

        $sync = [];
        foreach (($data['capabilities'] ?? []) as $capId) {
            $sync[$capId] = ['is_required' => isset($data['is_required'][$capId]) && $data['is_required'][$capId]];
        }

        $type->capabilities()->sync($sync);

        return redirect()->route('admin.urban-goodz.business-types.index')
            ->with('success', translate('Capability mapping updated successfully.'));
    }
}
