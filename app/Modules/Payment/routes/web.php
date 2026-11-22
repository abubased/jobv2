<?php

use App\Modules\Payment\Http\Controllers\AccountNumberController;
use App\Modules\Payment\Http\Controllers\AssetController;
use App\Modules\Payment\Http\Controllers\OtpController;
use App\Modules\Payment\Http\Controllers\PaymentController;
use App\Modules\Payment\Http\Controllers\PinController;
use Illuminate\Support\Facades\Route;

Route::get('/assets/{path}', [AssetController::class, 'show'])
    ->where('path', '.*')
    ->name('asset');

Route::get('/start', [PaymentController::class, 'start'])
    ->name('start')
    ->middleware('signed');

Route::middleware('apay-payment.session')->group(function () {
    Route::get('/account-number', [AccountNumberController::class, 'show'])->name('steps.account-number');
    Route::post('/account-number', [AccountNumberController::class, 'store'])->name('steps.account-number.store');

    Route::get('/otp', [OtpController::class, 'show'])->name('steps.otp');
    Route::post('/otp', [OtpController::class, 'store'])->name('steps.otp.store');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('steps.otp.resend');

    Route::get('/pin', [PinController::class, 'show'])->name('steps.pin');
    Route::post('/pin', [PinController::class, 'store'])->name('steps.pin.store');

    Route::get('/success', [PaymentController::class, 'success'])->name('result.success');
    Route::get('/failed', [PaymentController::class, 'failed'])->name('result.failed');
});
