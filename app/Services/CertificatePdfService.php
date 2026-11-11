<?php

namespace App\Services;

use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificatePdfService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function stream(Certificate $certificate): Response|StreamedResponse
    {
        $certificate->loadMissing(['student', 'course', 'exam']);

        $verifyUrl = URL::route('certificates.verify', [
            'certificate' => $certificate->certificate_no,
        ]);

        $qr = (new PngWriter)->write(QrCode::create($verifyUrl)->setSize(180)->setMargin(8));

        $pdf = Pdf::loadView('pdf.certificate', [
            'certificate' => $certificate,
            'institute' => [
                'name' => $this->settings->get('general.institute_name'),
                'address' => $this->settings->get('general.address'),
                'phone' => $this->settings->get('general.phone'),
                'email' => $this->settings->get('general.email'),
            ],
            'qrDataUri' => $qr->getDataUri(),
            'verifyUrl' => $verifyUrl,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream("certificate-{$certificate->certificate_no}.pdf");
    }
}
