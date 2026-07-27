<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Services\UrbanGoodz\VendorBusinessDirectoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UrbanGoodzVendorBusinessController extends Controller
{
    public function index(Request $request, VendorBusinessDirectoryService $directory)
    {
        $this->authorizeDirectory();

        $filters = $request->validate([
            'tab' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:150'],
            'module_id' => ['nullable', 'integer', 'min:1'],
            'zone_id' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:active,inactive,suspended'],
            'sort' => ['nullable', 'in:name,status,store_count,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'in:25,50,100'],
        ]);

        $tab = array_key_exists($filters['tab'] ?? '', VendorBusinessDirectoryService::TABS)
            ? $filters['tab']
            : 'accounts';

        return view('admin-views.urban-goodz.vendors-businesses.index', [
            'summary' => $directory->summary(),
            'records' => $directory->paginate(array_merge($filters, ['tab' => $tab])),
            'tabs' => VendorBusinessDirectoryService::TABS,
            'tab' => $tab,
            'filters' => $filters,
            'modules' => DB::table('modules')->select('id', 'module_name')->orderBy('module_name')->get(),
            'zones' => DB::table('zones')->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    private function authorizeDirectory(): void
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin && ((int) $admin->role_id === 1 || Helpers::module_permission_check('urban_goodz_view')),
            403,
            'You are not authorized to view Vendors & Businesses.'
        );
    }
}
