<?php

namespace App\Http\Controllers\Api\V1\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzBusinessType;
use App\Models\UrbanGoodzCapability;
use App\Services\Payments\PaymentSettings;
use Illuminate\Http\JsonResponse;

class UrbanGoodzAppConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $businessTypes = UrbanGoodzBusinessType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['slug', 'name', 'description', 'icon', 'sort_order']);

        $capabilities = UrbanGoodzCapability::orderBy('sort_order')
            ->get(['slug', 'name', 'description', 'admin_section_key', 'group', 'is_core', 'sort_order']);

        $adminSections = config('urban_goodz_admin_sections.capability_map', []);
        $groups = config('urban_goodz_admin_sections.groups', []);

        $formattedSections = [];
        foreach ($adminSections as $key => $section) {
            $formattedSections[$key] = [
                'label' => $section['label'],
                'icon' => $section['icon'],
                'group' => $section['group'],
                'group_label' => $groups[$section['group']]['label'] ?? $section['group'],
                'description' => $section['description'],
                'module_permission' => $section['module_permission'] ?? null,
                'availability_status' => $section['availability_status'] ?? 'backend_controlled',
            ];
        }

        return response()->json([
            'brand_name' => config('urban_goodz.brand_name', 'Urban Goodz'),
            'enabled_features' => $formattedSections,
            'business_types' => $businessTypes,
            'capabilities' => $capabilities,
            'admin_sections' => $formattedSections,
            'floating_ai_enabled' => config('urban_goodz.floating_ai_enabled', true),
            'order_anywhere_owner' => config('urban_goodz.order_anywhere_owner', 'master_admin'),
            'default_city' => config('urban_goodz.default_city', 'Houston'),
            'default_country' => config('urban_goodz.default_country', 'US'),
            'distance_unit' => config('urban_goodz.distance_unit', 'miles'),
            'currency' => config('urban_goodz.currency', 'USD'),
            'payment_mode' => app(PaymentSettings::class)->mode(),
            'adyen_enabled' => config('urban_goodz_payments.adyen.enabled', false),
            'staged_test_enabled' => config('urban_goodz_payments.staged_test.enabled', true),
            'business_type_groups' => $groups,
        ]);
    }
}
