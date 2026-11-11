<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ReceiptPdfService;
use App\Support\PermissionName;
use App\Support\PortalAccess;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceiptPdfController extends Controller
{
    public function __invoke(Receipt $receipt, ReceiptPdfService $pdfs): Response|StreamedResponse
    {
        $user = auth()->user();

        $canStaff = $user?->can(PermissionName::make('payment', 'view'))
            || $user?->can(PermissionName::make('fee', 'view'));
        $canPortal = PortalAccess::isStudentOrGuardian($user)
            && PortalAccess::canAccessStudent((int) $receipt->student_id, $user);

        abort_unless($canStaff || $canPortal, 403);

        return $pdfs->stream($receipt);
    }
}
