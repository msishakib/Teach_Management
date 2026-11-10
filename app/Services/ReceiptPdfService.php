<?php

namespace App\Services;

use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptPdfService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function download(Receipt $receipt): Response|StreamedResponse
    {
        $receipt->loadMissing(['student', 'account', 'allocations.invoice', 'createdBy']);

        $pdf = Pdf::loadView('pdf.receipt', $this->viewData($receipt));

        return $pdf->download("receipt-{$receipt->receipt_no}.pdf");
    }

    public function stream(Receipt $receipt): Response|StreamedResponse
    {
        $receipt->loadMissing(['student', 'account', 'allocations.invoice', 'createdBy']);

        $pdf = Pdf::loadView('pdf.receipt', $this->viewData($receipt));

        return $pdf->stream("receipt-{$receipt->receipt_no}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(Receipt $receipt): array
    {
        return [
            'receipt' => $receipt,
            'institute' => [
                'name' => $this->settings->get('general.institute_name'),
                'address' => $this->settings->get('general.address'),
                'phone' => $this->settings->get('general.phone'),
                'email' => $this->settings->get('general.email'),
                'logo' => $this->settings->get('general.logo'),
                'currency' => $this->settings->get('general.currency_symbol', '৳'),
            ],
        ];
    }
}
