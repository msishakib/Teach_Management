<?php

namespace App\Http\Controllers;

use App\Models\PaymentVoucher;
use App\Services\PaymentVoucherPdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentVoucherPdfController extends Controller
{
    public function __invoke(PaymentVoucher $paymentVoucher, PaymentVoucherPdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(
            auth()->user()?->can(PermissionName::make('payment', 'view'))
                || auth()->user()?->can(PermissionName::make('voucher', 'view')),
            403
        );

        return $pdfs->stream($paymentVoucher);
    }
}
