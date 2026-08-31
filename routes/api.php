<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReportController;
use App\Http\Controllers\TableController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/tables/schedule', [TableController::class, 'schedule']);
Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);

    Route::post('/payment/create', [PaymentController::class, 'create']);
    Route::get('/payment/status/{payment}', [PaymentController::class, 'status']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin/tables', [TableController::class, 'index']);
        Route::post('/tables', [TableController::class, 'store']);
        Route::put('/tables/{table}', [TableController::class, 'update']);
        Route::delete('/tables/{table}', [TableController::class, 'destroy']);
        Route::get('/admin/bookings', [BookingController::class, 'adminBookings']);
        Route::post('/admin/bookings/{booking}/cancel', [BookingController::class, 'adminCancel']);
        Route::get('/admin/payments', [PaymentController::class, 'adminPayments']);
        Route::get('/admin/payment-reports', [PaymentReportController::class, 'index']);
    });
});
