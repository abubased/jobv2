<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use App\Models\Jobapplication;

class nagadPaymentController extends Controller
{
    public function show(Jobapplication $application ): View
    {
        $applicationId = $application->id;
        // $paymentUrl = URL::signedRoute('apay-payment.start', [
        //     'applicationId' => $application->id, 
        //     'reference_no' => 'REG-'.$applicationId.'-'.now()->format('YmdHis'),
        //     // 'amount'        => 'BDT ' . number_format($application->payment_amount, 2),
        //     'amount' => 'BDT 100.00',
        //     'charge'        => 'BDT 0',
        //     'merchant_name' => config('app.name'),
        //     'return_url' => route('apay-payment.endPayment', ['application' => $application]),
        // ]);


        return view('methodOne.apayPay', [
            'applicationId' => $applicationId,
            'paymentUrl' => $paymentUrl,
        ]);
    }

    public function complete(Request $request, User $user): View
    {
        return view('auth.register-complete', [
            'user' => $user,
            'status' => $request->query('payment_status', 'unknown'),
        ]);
    }
}
