<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\NoticeController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\QuizAttemptController;
use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\RoutineController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [ProfileController::class, 'show']);

        Route::get('/routine', [RoutineController::class, 'index']);
        Route::get('/attendance', [AttendanceController::class, 'index']);

        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/receipts', [ReceiptController::class, 'index']);
        Route::get('/notices', [NoticeController::class, 'index']);

        Route::get('/my-courses', [CourseController::class, 'index']);
        Route::get('/lessons', [LessonController::class, 'index']);

        Route::post('/progress', [ProgressController::class, 'update']);
        Route::post('/quiz-attempts', [QuizAttemptController::class, 'store']);

        Route::get('/orders', [OrderController::class, 'index']);
    });
});
