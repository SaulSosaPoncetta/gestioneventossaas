<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Lo que retuvo la plataforma (cargo por servicio + comisión), por productora */
class AdminReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $from = $request->filled('desde') ? Carbon::parse($request->input('desde')) : now()->startOfMonth();
        $to = $request->filled('hasta') ? Carbon::parse($request->input('hasta')) : now();

        $rows = $reports->platformSummary($from, $to);

        return view('admin.reports', compact('rows', 'from', 'to'));
    }
}
