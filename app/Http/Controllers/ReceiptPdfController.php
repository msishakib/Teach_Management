<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ReceiptPdfService;
use App\Support\PermissionName;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptPdfController extends Controller
{
    public function __invoke(Receipt $receipt, ReceiptPdfService $pdfs): Response|StreamedResponse
    {
        abort_unless(
            auth()->user()?->can(PermissionName::make('payment', 'view'))
                || auth()->user()?->can(PermissionName::make('fee', 'view')),
            403
        );

        return $pdfs->stream($receipt);
    }
}
