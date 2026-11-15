<?php

namespace App\Http\Controllers\Admin\UrbanGoodz;

use App\Http\Controllers\Controller;
use App\Services\StrandedCoverageService;
use Illuminate\Http\Request;

class UrbanGoodzStrandedCoverageController extends Controller
{
    public function index(Request $request, StrandedCoverageService $coverage)
    {
        $days = min(365, max(1, (int) $request->query('days', 30)));
        $result = $coverage->analyze($days);

        return view('admin-views.urban-goodz.stranded.coverage', [
            'summary' => $result['summary'],
            'cells' => array_slice($result['cells'], 0, 60),
            'days' => $days,
        ]);
    }
}
