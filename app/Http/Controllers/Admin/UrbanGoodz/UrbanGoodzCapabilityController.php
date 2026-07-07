<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzCapability;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzCapabilityController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzCapability::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('slug', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('group')) {
            $query->where('group', $request->group);
        }

        if ($request->filled('admin_section_key')) {
            $query->where('admin_section_key', $request->admin_section_key);
        }

        $capabilities = $query->orderBy('group')->orderBy('sort_order')->orderBy('name')->paginate(25)->appends($request->query());

        $groups = UrbanGoodzCapability::select('group')->distinct()->whereNotNull('group')->pluck('group');
        $sectionKeys = UrbanGoodzCapability::select('admin_section_key')->distinct()->whereNotNull('admin_section_key')->pluck('admin_section_key');

        return view('admin-views.urban-goodz.capabilities.index', compact('capabilities', 'groups', 'sectionKeys'));
    }

    public function create()
    {
        $groups = UrbanGoodzCapability::select('group')->distinct()->whereNotNull('group')->pluck('group');
        $sectionKeys = UrbanGoodzCapability::select('admin_section_key')->distinct()->whereNotNull('admin_section_key')->pluck('admin_section_key');

        return view('admin-views.urban-goodz.capabilities.create', compact('groups', 'sectionKeys'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('urban_goodz_capabilities', 'slug')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'admin_section_key' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['is_core'] = $request->boolean('is_core', false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        UrbanGoodzCapability::create($data);

        return redirect()->route('admin.urban-goodz.capabilities.index')
            ->with('success', translate('Capability created successfully.'));
    }

    public function edit($id)
    {
        $capability = UrbanGoodzCapability::findOrFail($id);
        $groups = UrbanGoodzCapability::select('group')->distinct()->whereNotNull('group')->pluck('group');
        $sectionKeys = UrbanGoodzCapability::select('admin_section_key')->distinct()->whereNotNull('admin_section_key')->pluck('admin_section_key');

        return view('admin-views.urban-goodz.capabilities.edit', compact('capability', 'groups', 'sectionKeys'));
    }

    public function update(Request $request, $id)
    {
        $capability = UrbanGoodzCapability::findOrFail($id);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('urban_goodz_capabilities', 'slug')->ignore($capability->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'admin_section_key' => ['nullable', 'string', 'max:100'],
            'group' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $data['is_core'] = $request->boolean('is_core', false);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $capability->update($data);

        return redirect()->route('admin.urban-goodz.capabilities.index')
            ->with('success', translate('Capability updated successfully.'));
    }

    public function destroy($id)
    {
        $capability = UrbanGoodzCapability::findOrFail($id);
        $capability->businessTypes()->detach();
        $capability->delete();

        return back()->with('success', translate('Capability deleted successfully.'));
    }
}
