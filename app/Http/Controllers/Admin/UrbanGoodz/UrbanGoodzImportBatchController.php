<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Models\UrbanGoodzImportBatch;
use Illuminate\Http\Request;

class UrbanGoodzImportBatchController extends Controller
{
    public function index(Request $request)
    {
        $query = UrbanGoodzImportBatch::query();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('city', 'like', '%' . $request->search . '%')
                    ->orWhere('state', 'like', '%' . $request->search . '%')
                    ->orWhere('category', 'like', '%' . $request->search . '%')
                    ->orWhere('module', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->orderByDesc('created_at')->paginate(25)->appends($request->query());

        return view('admin-views.urban-goodz.import-batches.index', compact('batches'));
    }

    public function show($id)
    {
        $batch = UrbanGoodzImportBatch::with(['sourcedBusinesses', 'sourcedProducts'])->findOrFail($id);

        return view('admin-views.urban-goodz.import-batches.show', compact('batch'));
    }

    public function status($id, $status)
    {
        $batch = UrbanGoodzImportBatch::findOrFail($id);
        $batch->status = $status;
        $batch->save();

        return back()->with('success', translate('Import batch status updated successfully.'));
    }
}
