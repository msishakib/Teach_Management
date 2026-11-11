<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificatePdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificatePdfController extends Controller
{
    public function __invoke(Certificate $certificate, CertificatePdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(auth()->user()?->can(PermissionName::make('exam', 'view')), 403);

        return $pdfs->stream($certificate);
    }
}
