<?php

namespace App\Http\Controllers;

use App\Models\ReceiveVoucher;
use App\Services\ReceiveVoucherPdfService;
use App\Support\PermissionName;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiveVoucherPdfController extends Controller
{
    public function __invoke(ReceiveVoucher $receiveVoucher, ReceiveVoucherPdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(
            auth()->user()?->can(PermissionName::make('voucher', 'view'))
                || auth()->user()?->can(PermissionName::make('payment', 'view')),
            403
        );

        return $pdfs->stream($receiveVoucher);
    }
}
