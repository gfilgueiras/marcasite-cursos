<?php

use App\Http\Controllers\Api\V1\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\V1\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\V1\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\V1\Auth\LoginController;
use App\Http\Controllers\Api\V1\Public\CourseController;
use App\Http\Controllers\Api\V1\Public\EnrollmentController;
use App\Http\Controllers\Api\V1\Public\MyEnrollmentsController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [LoginController::class, 'login']);
    Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth:sanctum');

    Route::get('/courses', [CourseController::class, 'index']);

    Route::post('/enrollments', [EnrollmentController::class, 'store']);
    Route::post('/my-enrollments', MyEnrollmentsController::class)->middleware('throttle:30,1');

    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);

        Route::apiResource('courses', AdminCourseController::class);

        Route::get('/enrollments', [AdminEnrollmentController::class, 'index']);
        Route::delete('/enrollments/{enrollment}', [AdminEnrollmentController::class, 'destroy']);
    });

    Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
        Route::get('/enrollments/export', [AdminEnrollmentController::class, 'export']);
        Route::get('/enrollments/{enrollment}', [AdminEnrollmentController::class, 'show']);
        Route::put('/enrollments/{enrollment}', [AdminEnrollmentController::class, 'update']);
        Route::patch('/enrollments/{enrollment}', [AdminEnrollmentController::class, 'update']);
    });
});
