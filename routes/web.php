<?php

use App\Http\Controllers\CertificatePdfController;
use App\Http\Controllers\CertificateVerifyController;
use App\Http\Controllers\ExamAdmitCardPdfController;
use App\Http\Controllers\ExamFailListPdfController;
use App\Http\Controllers\ExamMarksheetPdfController;
use App\Http\Controllers\ExamMarksImportErrorController;
use App\Http\Controllers\ExamMeritListPdfController;
use App\Http\Controllers\ExamRoutinePdfController;
use App\Http\Controllers\ExamSubjectStatsPdfController;
use App\Http\Controllers\Media\AssignmentSubmissionDownloadController;
use App\Http\Controllers\Media\ChunkedUploadController;
use App\Http\Controllers\Media\LessonResourceDownloadController;
use App\Http\Controllers\Media\LessonVideoController;
use App\Http\Controllers\Media\MediaDownloadController;
use App\Http\Controllers\Media\MediaUploadController;
use App\Http\Controllers\Media\VideoStreamController;
use App\Http\Controllers\Payments\CheckoutController;
use App\Http\Controllers\Payments\FakePaymentController;
use App\Http\Controllers\Payments\ManualPaymentController;
use App\Http\Controllers\Payments\PaymentCallbackController;
use App\Http\Controllers\PaymentVoucherPdfController;
use App\Http\Controllers\Public\AdmissionController;
use App\Http\Controllers\Public\AssignmentSubmissionController;
use App\Http\Controllers\Public\CourseController;
use App\Http\Controllers\Public\CoursePlayerController;
use App\Http\Controllers\Public\OnlineEnrollmentController;
use App\Http\Controllers\Public\QuizAttemptController;
use App\Http\Controllers\Public\StudentRegistrationController;
use App\Http\Controllers\ReceiptPdfController;
use App\Http\Controllers\ReceiveVoucherPdfController;
use App\Http\Controllers\ReportExportDownloadController;
use App\Http\Controllers\Site\GalleryController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\InquiryController;
use App\Http\Controllers\Site\LocaleController;
use App\Http\Controllers\Site\NoticeController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\TwoFactorChallengeController;
use App\Http\Middleware\SetPublicLocale;
use App\Http\Middleware\UsePreferredLocale;
use Illuminate\Support\Facades\Route;

// Public website (separate from the Filament panels). Each language lives under its own prefix.
Route::get('/', [LocaleController::class, 'root'])->name('root');

Route::prefix('{locale}')
    ->where(['locale' => SetPublicLocale::pattern()])
    ->middleware(SetPublicLocale::class)
    ->group(function () {
        Route::get('/', HomeController::class)->name('home');
        Route::get('/register', [StudentRegistrationController::class, 'show'])->name('register');
        Route::post('/register', [StudentRegistrationController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('register.store');
        Route::get('/register/success/{registration:registration_no}', [StudentRegistrationController::class, 'success'])
            ->name('register.success');
        Route::get('/apply', [AdmissionController::class, 'showApply'])->name('apply');
        Route::post('/apply', [AdmissionController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('apply.store');
        Route::get('/apply/success/{application_no}', [AdmissionController::class, 'success'])->name('admissions.success');
        Route::get('/track-application', [AdmissionController::class, 'trackForm'])->name('admissions.track.form');
        Route::post('/track-application', [AdmissionController::class, 'track'])
            ->middleware('throttle:12,1')
            ->name('admissions.track');
        Route::post('/contact', [InquiryController::class, 'storeContact'])
            ->middleware('throttle:6,1')
            ->name('contact.store');
        Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
        Route::get('/notices/{notice}/{slug?}', [NoticeController::class, 'show'])
            ->whereNumber('notice')
            ->name('notices.show');
        Route::get('/gallery', GalleryController::class)->name('gallery');

        // Online course catalogue, sales page and learner player.
        Route::get('/courses/{course:code}', CourseController::class)->name('courses.show');
        Route::get('/courses/{course:code}/learn/{lesson?}', CoursePlayerController::class)
            ->middleware('auth')
            ->name('courses.learn');
        Route::post('/courses/{course:code}/enroll', OnlineEnrollmentController::class)
            ->middleware(['auth', 'throttle:12,1'])
            ->name('courses.enroll');
        Route::get('/courses/{course:code}/checkout', [CheckoutController::class, 'show'])
            ->middleware('auth')
            ->name('courses.checkout');
        Route::post('/courses/{course:code}/checkout', [CheckoutController::class, 'store'])
            ->middleware(['auth', 'throttle:12,1'])
            ->name('courses.checkout.store');

        Route::middleware('auth')->group(function () {
            Route::get('/courses/{course:code}/quizzes/{quiz}', [QuizAttemptController::class, 'show'])
                ->name('courses.quizzes.show');
            Route::post('/courses/{course:code}/quizzes/{quiz}/start', [QuizAttemptController::class, 'start'])
                ->middleware('throttle:30,1')
                ->name('courses.quizzes.start');
            Route::get('/courses/{course:code}/quizzes/{quiz}/attempts/{attempt}', [QuizAttemptController::class, 'attempt'])
                ->name('courses.quizzes.attempts.show');
            Route::post('/courses/{course:code}/quizzes/{quiz}/attempts/{attempt}/questions/{question}', [QuizAttemptController::class, 'saveAnswer'])
                ->middleware('throttle:120,1')
                ->name('courses.quizzes.attempts.save');
            Route::post('/courses/{course:code}/quizzes/{quiz}/attempts/{attempt}/submit', [QuizAttemptController::class, 'submit'])
                ->middleware('throttle:30,1')
                ->name('courses.quizzes.attempts.submit');

            Route::get('/courses/{course:code}/assignments/{assignment}', [AssignmentSubmissionController::class, 'show'])
                ->name('courses.assignments.show');
            Route::post('/courses/{course:code}/assignments/{assignment}', [AssignmentSubmissionController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('courses.assignments.submit');
        });
    });

Route::get('/{path}', [LocaleController::class, 'legacy'])
    ->where('path', 'apply|register|gallery|notices(/[0-9]+)?')
    ->name('legacy');

// Lesson media is access-checked by its policy, so free previews work for guests.
Route::get('/lessons/{lesson}/video', LessonVideoController::class)
    ->middleware('throttle:120,1')
    ->name('lessons.video');

Route::get('/lessons/{lesson}/resources/{resource}/download', LessonResourceDownloadController::class)
    ->middleware('throttle:120,1')
    ->name('lessons.resources.download');

Route::get('/assignment-submissions/{submission}/files/{media}', AssignmentSubmissionDownloadController::class)
    ->middleware(['auth', 'throttle:120,1'])
    ->name('assignments.submissions.download');

Route::get('/media/{media}/stream', VideoStreamController::class)
    ->middleware('throttle:120,1')
    ->name('media.video');

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::get('/certificates/verify/{certificate}', CertificateVerifyController::class)
    ->name('certificates.verify');

// Payment gateway return URLs + IPN (CSRF excluded in bootstrap/app.php).
Route::prefix('payments')
    ->name('payments.')
    ->middleware(UsePreferredLocale::class)
    ->group(function () {
        Route::match(['get', 'post'], '/sslcommerz/success', [PaymentCallbackController::class, 'success'])
            ->name('sslcommerz.success');
        Route::match(['get', 'post'], '/sslcommerz/fail', [PaymentCallbackController::class, 'fail'])
            ->name('sslcommerz.fail');
        Route::match(['get', 'post'], '/sslcommerz/cancel', [PaymentCallbackController::class, 'cancel'])
            ->name('sslcommerz.cancel');
        Route::post('/sslcommerz/ipn', [PaymentCallbackController::class, 'ipn'])
            ->name('sslcommerz.ipn');

        Route::match(['get', 'post'], '/bkash/callback', [PaymentCallbackController::class, 'bkashCallback'])
            ->name('bkash.callback');
        Route::post('/bkash/ipn', [PaymentCallbackController::class, 'ipn'])
            ->name('bkash.ipn');

        Route::get('/success/{order}', [PaymentCallbackController::class, 'showSuccess'])->name('success');
        Route::get('/failed/{order}', [PaymentCallbackController::class, 'showFailed'])->name('failed');
        Route::get('/cancelled/{order}', [PaymentCallbackController::class, 'showCancelled'])->name('cancelled');

        Route::middleware('auth')->group(function () {
            Route::get('/manual/{order}', [ManualPaymentController::class, 'show'])->name('manual.show');
            Route::post('/manual/{order}', [ManualPaymentController::class, 'submit'])
                ->middleware('throttle:12,1')
                ->name('manual.submit');
            Route::get('/manual/{order}/submitted', [ManualPaymentController::class, 'submitted'])
                ->name('manual.submitted');

            Route::get('/fake/{order}', [FakePaymentController::class, 'show'])->name('fake.pay');
            Route::post('/fake/{order}', [FakePaymentController::class, 'pay'])
                ->middleware('throttle:30,1')
                ->name('fake.pay.submit');
        });
    });

Route::middleware('guest')->group(function () {
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])
        ->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('two-factor.challenge.verify');
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

    // Central media module: private downloads, lesson video streaming and uploads.
    Route::get('/media/{media}/download', MediaDownloadController::class)
        ->name('media.download');

    Route::post('/media/upload', MediaUploadController::class)
        ->name('media.upload');

    Route::post('/media/chunked', [ChunkedUploadController::class, 'initiate'])
        ->name('media.chunked.initiate');

    Route::get('/media/chunked/{uploadId}', [ChunkedUploadController::class, 'status'])
        ->name('media.chunked.status');

    Route::post('/media/chunked/{uploadId}', [ChunkedUploadController::class, 'append'])
        ->name('media.chunked.append');

    Route::post('/media/chunked/{uploadId}/complete', [ChunkedUploadController::class, 'complete'])
        ->name('media.chunked.complete');

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
