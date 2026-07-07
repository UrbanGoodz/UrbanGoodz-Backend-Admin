<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzAIConversation;
use App\Models\UrbanGoodzAIIntent;
use Illuminate\Http\Request;

class UrbanGoodzAIConciergeController extends Controller
{
    public function intents()
    {
        $intents = UrbanGoodzAIIntent::orderBy('sort_order')->paginate(25);

        return view('admin-views.urban-goodz.ai-concierge.intents', [
            'intents' => $intents,
        ]);
    }

    public function intentsStore(Request $request)
    {
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:urban_goodz_ai_intents,slug'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string'],
            'response_template' => ['nullable', 'string'],
            'capability_slug' => ['nullable', 'string', 'max:100'],
            'admin_section_key' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['keywords'] = $data['keywords']
            ? array_map('trim', explode(',', $data['keywords']))
            : [];
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        UrbanGoodzAIIntent::create($data);

        return back()->with('success', translate('AI intent created successfully.'));
    }

    public function intentsUpdate($id, Request $request)
    {
        $intent = UrbanGoodzAIIntent::findOrFail($id);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:100', 'unique:urban_goodz_ai_intents,slug,' . $id],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'keywords' => ['nullable', 'string'],
            'response_template' => ['nullable', 'string'],
            'capability_slug' => ['nullable', 'string', 'max:100'],
            'admin_section_key' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['keywords'] = $data['keywords']
            ? array_map('trim', explode(',', $data['keywords']))
            : [];
        $data['is_active'] = $request->boolean('is_active', $intent->is_active);
        $data['sort_order'] = $data['sort_order'] ?? $intent->sort_order;

        $intent->update($data);

        return back()->with('success', translate('AI intent updated successfully.'));
    }

    public function intentsDestroy($id)
    {
        $intent = UrbanGoodzAIIntent::findOrFail($id);
        $intent->conversations()->update(['detected_intent_id' => null]);
        $intent->delete();

        return back()->with('success', translate('AI intent deleted successfully.'));
    }

    public function conversations()
    {
        $conversations = UrbanGoodzAIConversation::with('detectedIntent')
            ->latest()
            ->paginate(25);

        return view('admin-views.urban-goodz.ai-concierge.conversations', [
            'conversations' => $conversations,
        ]);
    }

    public function conversationsShow($id)
    {
        $conversation = UrbanGoodzAIConversation::with('detectedIntent')->findOrFail($id);

        return view('admin-views.urban-goodz.ai-concierge.show', [
            'conversation' => $conversation,
        ]);
    }

    public function conversationsUpdate($id, Request $request)
    {
        $conversation = UrbanGoodzAIConversation::findOrFail($id);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,resolved,escalated'],
            'admin_notes' => ['nullable', 'string'],
        ]);

        $conversation->update($data);

        return back()->with('success', translate('Conversation updated successfully.'));
    }
}
