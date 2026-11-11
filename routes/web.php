<?php

use App\Http\Controllers\CertificatePdfController;
use App\Http\Controllers\CertificateVerifyController;
use App\Http\Controllers\ExamAdmitCardPdfController;
use App\Http\Controllers\ExamFailListPdfController;
use App\Http\Controllers\ExamMarksImportErrorController;
use App\Http\Controllers\ExamMarksheetPdfController;
use App\Http\Controllers\ExamMeritListPdfController;
use App\Http\Controllers\ExamRoutinePdfController;
use App\Http\Controllers\ExamSubjectStatsPdfController;
use App\Http\Controllers\PaymentVoucherPdfController;
use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\ReceiveVoucherPdfController;
use App\Http\Controllers\ReportExportDownloadController;
use App\Http\Controllers\Site\GalleryController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\InquiryController;
use App\Http\Controllers\Public\AdmissionController;
use App\Http\Controllers\Site\LocaleController;
use App\Http\Controllers\Site\NoticeController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Middleware\SetPublicLocale;
use Illuminate\Support\Facades\Route;

// Public website (separate from the Filament panels). Each language lives under its own prefix.
Route::get('/', [LocaleController::class, 'root'])->name('root');

Route::prefix('{locale}')
    ->where(['locale' => SetPublicLocale::pattern()])
    ->middleware(SetPublicLocale::class)
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/apply', [AdmissionController::class, 'showApply'])->name('apply');
        Route::post('/apply', [AdmissionController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('apply.store');
        Route::get('/apply/success/{application_no}', [AdmissionController::class, 'success'])->name('admissions.success');
        Route::get('/track-application', [AdmissionController::class, 'trackForm'])->name('admissions.track.form');
        Route::post('/track-application', [AdmissionController::class, 'track'])->name('admissions.track');
        Route::post('/contact', [InquiryController::class, 'storeContact'])
            ->middleware('throttle:6,1')
            ->name('contact.store');
        Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
        Route::get('/notices/{notice}/{slug?}', [NoticeController::class, 'show'])
            ->whereNumber('notice')
            ->name('notices.show');
        Route::get('/gallery', GalleryController::class)->name('gallery');
    });

Route::get('/{path}', [LocaleController::class, 'legacy'])
    ->where('path', 'apply|gallery|notices(/[0-9]+)?')
    ->name('legacy');

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('/certificates/verify/{certificate}', CertificateVerifyController::class)
    ->name('certificates.verify');

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

    Route::get('/exams/marks/import-errors', ExamMarksImportErrorController::class)
        ->name('exams.marks.import-errors');

    Route::get('/exam-results/{examResult}/marksheet.pdf', ExamMarksheetPdfController::class)
        ->name('exam-results.marksheet.pdf');

    Route::get('/exams/{exam}/merit-list.pdf', ExamMeritListPdfController::class)
        ->name('exams.merit-list.pdf');

    Route::get('/exams/{exam}/fail-list.pdf', ExamFailListPdfController::class)
        ->name('exams.fail-list.pdf');

    Route::get('/exams/{exam}/subject-stats.pdf', ExamSubjectStatsPdfController::class)
        ->name('exams.subject-stats.pdf');

    Route::get('/certificates/{certificate}/pdf', CertificatePdfController::class)
        ->name('certificates.pdf');
});

