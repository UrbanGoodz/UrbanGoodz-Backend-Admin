<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzPlusMembership;
use Illuminate\Http\Request;

class UrbanGoodzPlusMembershipController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzPlusMembership::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('member_name', 'like', '%' . $request->search . '%')
                    ->orWhere('member_email', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('tier')) {
            $query->where('tier', $request->tier);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $memberships = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.plus-membership.index', compact('memberships'));
    }

    public function show($id)
    {
        $membership = UrbanGoodzPlusMembership::findOrFail($id);

        return view('admin-views.urban-goodz.plus-membership.show', compact('membership'));
    }

    public function create()
    {
        return view('admin-views.urban-goodz.plus-membership.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'member_name' => ['required', 'string', 'max:255'],
            'member_email' => ['required', 'email', 'max:255'],
            'tier' => ['required', 'string', 'in:basic,premium,elite'],
            'status' => ['required', 'string', 'max:50'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'subscribed_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'benefits' => ['nullable', 'string'],
        ]);

        $data['benefits'] = $data['benefits'] ? array_map('trim', explode("\n", $data['benefits'])) : [];

        UrbanGoodzPlusMembership::create($data);

        return redirect()->route('admin.urban-goodz.plus-membership.index')
            ->with('success', translate('Membership created successfully.'));
    }

    public function edit($id)
    {
        $membership = UrbanGoodzPlusMembership::findOrFail($id);

        return view('admin-views.urban-goodz.plus-membership.edit', compact('membership'));
    }

    public function update(Request $request, $id)
    {
        $membership = UrbanGoodzPlusMembership::findOrFail($id);

        $data = $request->validate([
            'member_name' => ['required', 'string', 'max:255'],
            'member_email' => ['required', 'email', 'max:255'],
            'tier' => ['required', 'string', 'in:basic,premium,elite'],
            'status' => ['required', 'string', 'max:50'],
            'monthly_fee' => ['required', 'numeric', 'min:0'],
            'subscribed_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'benefits' => ['nullable', 'string'],
        ]);

        $data['benefits'] = $data['benefits'] ? array_map('trim', explode("\n", $data['benefits'])) : [];

        $membership->update($data);

        return redirect()->route('admin.urban-goodz.plus-membership.index')
            ->with('success', translate('Membership updated successfully.'));
    }

    public function destroy($id)
    {
        $membership = UrbanGoodzPlusMembership::findOrFail($id);
        $membership->delete();

        return back()->with('success', translate('Membership deleted successfully.'));
    }

    public function status($id, $status)
    {
        $membership = UrbanGoodzPlusMembership::findOrFail($id);
        $membership->status = $status;
        $membership->save();

        return back()->with('success', translate('Membership status updated successfully.'));
    }
}
