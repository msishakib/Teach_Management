<?php

use App\Http\Controllers\ExamAdmitCardPdfController;
use App\Http\Controllers\ExamRoutinePdfController;
use App\Http\Controllers\PaymentVoucherPdfController;
use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\ReceiveVoucherPdfController;
use App\Http\Controllers\ReportExportDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/receipts/{receipt}/pdf', ReceiptPdfController::class)
        ->name('receipts.pdf');

    Route::get('/payment-vouchers/{paymentVoucher}/pdf', PaymentVoucherPdfController::class)
        ->name('payment-vouchers.pdf');

    Route::get('/receive-vouchers/{receiveVoucher}/pdf', ReceiveVoucherPdfController::class)
        ->name('receive-vouchers.pdf');

    Route::get('/reports/exports/download', ReportExportDownloadController::class)
        ->name('reports.export.download');

    Route::get('/exams/{exam}/routine.pdf', ExamRoutinePdfController::class)
        ->name('exams.routine.pdf');

    Route::get('/exams/{exam}/admit-cards/{student}.pdf', ExamAdmitCardPdfController::class)
        ->name('exams.admit-card.pdf');
});

