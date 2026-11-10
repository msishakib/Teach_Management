<?php

namespace App\Services;

use App\Models\ReceiveVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiveVoucherPdfService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function download(ReceiveVoucher $voucher): Response|StreamedResponse
    {
        $voucher->loadMissing([
            'payer',
            'creditAccount',
            'receivedToAccount',
            'createdBy',
            'approvedBy',
        ]);

        return Pdf::loadView('pdf.receive-voucher', $this->viewData($voucher))
            ->download("receive-voucher-{$voucher->voucher_no}.pdf");
    }

    public function stream(ReceiveVoucher $voucher): Response|StreamedResponse
    {
        $voucher->loadMissing([
            'payer',
            'creditAccount',
            'receivedToAccount',
            'createdBy',
            'approvedBy',
        ]);

        return Pdf::loadView('pdf.receive-voucher', $this->viewData($voucher))
            ->stream("receive-voucher-{$voucher->voucher_no}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(ReceiveVoucher $voucher): array
    {
        return [
            'voucher' => $voucher,
            'institute' => [
                'name' => $this->settings->get('general.institute_name'),
                'address' => $this->settings->get('general.address'),
                'phone' => $this->settings->get('general.phone'),
                'email' => $this->settings->get('general.email'),
                'currency' => $this->settings->get('general.currency_symbol', '৳'),
            ],
        ];
    }
}
