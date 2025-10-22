<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Customer\PaymongoController;

Route::middleware(['auth:sanctum'])->group(function () {
Route::post('/customer/payment/create', [PaymongoController::class, 'createPayment'])->name('payment.create');
Route::get('/payment/success', [PaymongoController::class, 'success'])->name('payment.success');
Route::get('/payment/cancelled', [PaymongoController::class, 'cancelled'])->name('payment.cancelled');
Route::post('/payment/webhook', [PaymongoController::class, 'webhook'])->name('payment.webhook');
});


