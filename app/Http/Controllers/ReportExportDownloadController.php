<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportExportService;
use App\Support\PermissionName;
use Illuminate\Http\Request;

class ReportExportDownloadController extends Controller
{
    public function __invoke(Request $request, FinancialReportExportService $exports)
    {
        abort_unless($request->hasValidSignature(), 403);
        abort_unless(
            auth()->user()?->can(PermissionName::make('report', 'view'))
                || auth()->user()?->can(PermissionName::make('report', 'export')),
            403
        );

        $path = decrypt($request->query('path'));

        return $exports->download($path);
    }
}
