<?php

namespace App\Services;

use App\Models\PaymentVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentVoucherPdfService
{
    public function __construct(
        protected SettingsService $settings,
    ) {}

    public function download(PaymentVoucher $voucher): Response|StreamedResponse
    {
        $voucher->loadMissing([
            'payee',
            'expenseAccount',
            'paidFromAccount',
            'createdBy',
            'approvedBy',
            'teacherSalary.teacher',
        ]);

        return Pdf::loadView('pdf.payment-voucher', $this->viewData($voucher))
            ->download("payment-voucher-{$voucher->voucher_no}.pdf");
    }

    public function stream(PaymentVoucher $voucher): Response|StreamedResponse
    {
        $voucher->loadMissing([
            'payee',
            'expenseAccount',
            'paidFromAccount',
            'createdBy',
            'approvedBy',
            'teacherSalary.teacher',
        ]);

        return Pdf::loadView('pdf.payment-voucher', $this->viewData($voucher))
            ->stream("payment-voucher-{$voucher->voucher_no}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(PaymentVoucher $voucher): array
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
