<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Modules\Payment\Services\PaymentSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Owns the payment flow's lifecycle boundaries: opening a fresh session from
 * a signed URL and, later, rendering the terminal success/failed screens.
 * Per-step verification lives in its own controller (AccountNumberController,
 * OtpController, PinController) to keep each class single-purpose.
 */
class PaymentController extends Controller
{
    public function __construct(private PaymentSession $session) {}

    public function start(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $this->session->forget();
        $this->session->put([
            'reference_no' => $request->query('reference_no'),
            'amount' => $request->query('amount'),
            'charge' => $request->query('charge'),
            'merchant_name' => $request->query('merchant_name'),
            'method' => $request->query('method'),
            'return_url' => $request->query('return_url'),
        ]);

        $this->session->setStep('account-number');

        return redirect()->route('apay-payment.steps.account-number');
    }

    public function success(): View|RedirectResponse
    {
        if ($this->session->get('status') !== 'success') {
            return $this->redirectToCurrentStep();
        }
        $method = $this->session->get('method');

        return match ($method) {
            'bkash' => view('payment::steps.bkash.success', [
                'session' => $this->session,
            ]),

            'nagad' => view('payment::steps.nagad.success', [
                'session' => $this->session,
            ]),

            'rocket' => view('payment::steps.rocket.success', [
                'session' => $this->session,
            ]),

            'upay' => view('payment::steps.upay.success', [
                'session' => $this->session,
            ]),

            default => abort(400, 'Invalid payment method'),
        };
    }

    public function failed(): View|RedirectResponse
    {
        if ($this->session->get('status') !== 'failed') {
            return $this->redirectToCurrentStep();
        }

        $method = $this->session->get('method');

        return match ($method) {
            'bkash' => view('payment::steps.bkash.failed', [
                'session' => $this->session,
            ]),

            'nagad' => view('payment::steps.nagad.failed', [
                'session' => $this->session,
            ]),

            'rocket' => view('payment::steps.rocket.failed', [
                'session' => $this->session,
            ]),

            'upay' => view('payment::steps.upay.failed', [
                'session' => $this->session,
            ]),

            default => abort(400, 'Invalid payment method'),
        };
    }

    private function redirectToCurrentStep(): RedirectResponse
    {
        return redirect()->route('apay-payment.steps.'.($this->session->step() ?? 'account-number'));
    }
}
