<?php

namespace Modules\AI\app\Http\Controllers\Admin\Web\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Brian2694\Toastr\Facades\Toastr;

class AISettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            'openai_api_key' => config('openai.api_key') ? '••••••' . substr(config('openai.api_key'), -4) : 'Not configured',
            'openai_organization' => config('openai.organization') ?? 'Not set',
            'openai_timeout' => config('openai.request_timeout', 60),
            'engines' => [
                'openai' => ['name' => 'OpenAI GPT-4o', 'available' => !empty(config('openai.api_key'))],
                'deepseek' => ['name' => 'DeepSeek (coming soon)', 'available' => false],
                'claude' => ['name' => 'Claude (coming soon)', 'available' => false],
            ],
            'active_engine' => 'openai',
            'features' => [
                'product_autofill' => ['enabled' => true, 'description' => 'AI-powered product title, description, and SEO generation'],
                'image_analysis' => ['enabled' => true, 'description' => 'Auto-detect product details from uploaded images'],
                'seo_optimization' => ['enabled' => true, 'description' => 'Generate SEO-friendly meta titles, descriptions, and keywords'],
                'price_suggestion' => ['enabled' => true, 'description' => 'AI-suggested pricing based on market data'],
            ],
        ];

        return view('admin-views.ai.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'openai_api_key' => 'nullable|string',
            'openai_organization' => 'nullable|string',
            'active_engine' => 'required|in:openai',
        ]);

        $currentKey = config('openai.api_key') ? '••••••' . substr(config('openai.api_key'), -4) : '';

        if ($request->filled('openai_api_key') && $request->openai_api_key !== $currentKey) {
            Toastr::info('API key changes require updating your .env file and running: php artisan config:clear');
        } else {
            Toastr::success('AI settings updated');
        }

        return redirect()->route('admin.ai.settings');
    }
}
