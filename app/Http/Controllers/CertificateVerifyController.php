<?php

namespace App\Http\Controllers;

use App\Services\CertificateService;
use Illuminate\Contracts\View\View;

class CertificateVerifyController extends Controller
{
    public function __invoke(string $certificate, CertificateService $certificates): View
    {
        return view('certificates.verify', [
            'certificate' => $certificates->findPublic($certificate),
        ]);
    }
}
