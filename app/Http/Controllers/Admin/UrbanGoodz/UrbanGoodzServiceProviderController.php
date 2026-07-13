<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzServiceProvider;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UrbanGoodzServiceProviderController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzServiceProvider::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('business_name', 'like', '%' . $request->search . '%')
                    ->orWhere('contact_name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('service_category')) {
            $query->where('service_category', $request->service_category);
        }

        $providers = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.service-providers.index', compact('providers'));
    }

    public function show($id)
    {
        $provider = UrbanGoodzServiceProvider::findOrFail($id);

        return view('admin-views.urban-goodz.service-providers.show', compact('provider'));
    }

    public function create()
    {
        return view('admin-views.urban-goodz.service-providers.create');
    }

    public function store(Request $request)
    {
        if (!Helpers::module_permission_check('urban_goodz_service_provider_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('urban_goodz_service_providers', 'slug')],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'service_category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'service_areas' => ['nullable', 'string'],
        ]);

        $data['is_verified'] = $request->boolean('is_verified');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['service_areas'] = $data['service_areas'] ? array_map('trim', explode("\n", $data['service_areas'])) : [];

        UrbanGoodzServiceProvider::create($data);

        return redirect()->route('admin.urban-goodz.service-providers.index')
            ->with('success', translate('Service provider created successfully.'));
    }

    public function edit($id)
    {
        $provider = UrbanGoodzServiceProvider::findOrFail($id);

        return view('admin-views.urban-goodz.service-providers.edit', compact('provider'));
    }

    public function update(Request $request, $id)
    {
        if (!Helpers::module_permission_check('urban_goodz_service_provider_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $provider = UrbanGoodzServiceProvider::findOrFail($id);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('urban_goodz_service_providers', 'slug')->ignore($provider->id)],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'service_category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'service_areas' => ['nullable', 'string'],
        ]);

        $data['is_verified'] = $request->boolean('is_verified');
        $data['is_active'] = $request->boolean('is_active', true);
        $data['service_areas'] = $data['service_areas'] ? array_map('trim', explode("\n", $data['service_areas'])) : [];

        $provider->update($data);

        return redirect()->route('admin.urban-goodz.service-providers.index')
            ->with('success', translate('Service provider updated successfully.'));
    }

    public function destroy($id)
    {
        if (!Helpers::module_permission_check('urban_goodz_service_provider_manage')) {
            Toastr::error(translate('messages.access_denied'));
            return back();
        }

        $provider = UrbanGoodzServiceProvider::findOrFail($id);
        $provider->delete();

        return back()->with('success', translate('Service provider deleted successfully.'));
    }

    public function status($id, $status)
    {
        $provider = UrbanGoodzServiceProvider::findOrFail($id);
        $provider->is_active = $status === '1';
        $provider->save();

        return back()->with('success', translate('Service provider status updated successfully.'));
    }
}
