<?php

namespace App\Http\Controllers;

use App\Events\JobApplicationCreated;
use App\Helpers\MetaTagHelper;
use App\Models\JobApplication;
use App\Models\LalcoinApplication;
use App\Notifications\NewJobApplicationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Inertia;

class JobApplicationController extends Controller
{
    /**
     * Display a listing of job applications
     */
    public function index()
    {
        return Inertia::render('Applications/Index', [
            'applications' => JobApplication::latest()->paginate(20)->withQueryString(),
        ])->withViewData(MetaTagHelper::forApplications());
    }
    public function coinApplications()
    {
        return Inertia::render('LalcoinApplications/Index', [
            'applications' => LalcoinApplication::latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }



    /**
     * Store a newly created job application
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'father_or_husband' => 'nullable|string|max:255',
            'mother' => 'nullable|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'education' => 'required|string|in:primary,secondary,higher_secondary,bachelor,masters',
            'courier_help' => 'required|string|in:yes,no',
            'nearest_courier' => 'nullable|string|max:255',
            'payment_number' => 'nullable|string|max:255',
            'payment_amount' => 'nullable|string|max:255',
            'submitted' => 'boolean',
        ]);

        // if (JobApplication::where('phone', $validated['phone'])->exists()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'আপনার ' . $validated['phone'] . ' নাম্বারে আগেই ফরম পূরণ হয়েছে।',
        //         'errors' => [
        //             'phone' => 'আপনার ' . $validated['phone'] . ' নাম্বারে আগেই ফরম পূরণ হয়েছে।',
        //         ],
        //     ], 422);
        // }

        $application = JobApplication::create($validated);
        event(new JobApplicationCreated($application));

        Notification::route(
            'mail',
            config('mail.admin_notification_email')
        )->notify(
            new NewJobApplicationNotification($application)
        );

        return response()->json([
            'success' => true,
            'message' => 'Application created successfully.',
            'application' => [
                'id' => $application->id,
                'name' => $application->name,
                'phone' => $application->phone,
            ],
        ]);
    }



    public function initPayment(Request $request)
    {
        $validated = $request->validate([
            'application_id' => 'required|exists:job_applications,id',
            'method' => 'required|string|in:nagad,bkash,rocket,upay',
        ]);

        $application = JobApplication::findOrFail(
            $validated['application_id']
        );

        /*
        |--------------------------------------------------------------------------
        | Already initialized?
        |--------------------------------------------------------------------------
        */

        if (
            $application->payment_method === $validated['method'] &&
            $application->payment_reference &&
            $application->payment_url
        ) {
            return response()->json([
                'success' => true,
                'method' => $validated['method'],
                'application_id' => $application->id,
                'url' => $application->payment_url,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent simultaneous requests
        |--------------------------------------------------------------------------
        */

        $lock = Cache::lock(
            'payment-init:' . $application->id,
            30
        );

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'পেমেন্ট প্রস্তুত হচ্ছে। অনুগ্রহ করে কিছুক্ষণ অপেক্ষা করুন।',
            ], 409);
        }

        try {

            /*
            |--------------------------------------------------------------------------
            | Check again after acquiring lock
            |--------------------------------------------------------------------------
            */

            $application->refresh();

            if (
                $application->payment_method === $validated['method'] &&
                $application->payment_reference &&
                $application->payment_url
            ) {
                return response()->json([
                    'success' => true,
                    'method' => $validated['method'],
                    'application_id' => $application->id,
                    'url' => $application->payment_url,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Method name
            |--------------------------------------------------------------------------
            */

            $methodName = match ($validated['method']) {
                'bkash' => 'bKash',
                'nagad' => 'Nagad',
                'rocket' => 'Rocket',
                'upay' => 'Upay',
            };

            $amount = number_format(
                (float) $application->nearest_courier,
                2,
                '.',
                ''
            );

            /*
            |--------------------------------------------------------------------------
            | Generate ONE reference
            |--------------------------------------------------------------------------
            */

            $referenceNo =
                'REG-' .
                $application->id .
                '-' .
                strtoupper(Str::random(8));

            /*
            |--------------------------------------------------------------------------
            | Create payment URL
            |--------------------------------------------------------------------------
            */

            $paymentUrl = URL::signedRoute(
                'apay-payment.start',
                [
                    'reference_no' => $referenceNo,
                    'amount' => $amount,
                    'charge' => '0.00',
                    'merchant_name' => config('app.name'),
                    'method' => $validated['method'],
                    'return_url' => route('payment.callback', [
                        'application_id' => $application->id,
                        'method' => $validated['method'],
                    ]),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Save payment information
            |--------------------------------------------------------------------------
            */

            $application->update([
                'payment_method' => $validated['method'],
                'payment_reference' => $referenceNo,
                'payment_url' => $paymentUrl,
                'payment_status' => 'initiated',
                'submitted' => true,
            ]);

            return response()->json([
                'success' => true,
                'method' => $validated['method'],
                'method_name' => $methodName,
                'application_id' => $application->id,
                'url' => $paymentUrl,
            ]);

        } finally {

            $lock->release();
        }
    }





    // public function initPayment(Request $request)
    // {
    //     $validated = $request->validate([
    //         'application_id' => 'required|exists:job_applications,id',
    //         'method' => 'required|string|in:nagad,bkash,rocket,upay',
    //     ]);
        
    //     $application = JobApplication::findOrFail($validated['application_id']);

    //     $methodName = match ($validated['method']) {
    //         'bkash' => 'bKash',
    //         'nagad' => 'Nagad',
    //         'rocket' => 'Rocket',
    //         'upay' => 'Upay',
    //     };

    //     $amount = '99.00';

    //     $paymentUrl = URL::signedRoute('apay-payment.start', [
    //         'reference_no' => 'REG-' . $application->id . '-' . strtoupper(Str::random(8)),
    //         'amount' => $amount,
    //         'charge' => '0.00',
    //         'merchant_name' => config('app.name'),
    //         'method' => $validated['method'],
    //         'return_url' => route('payment.callback', [
    //             'application_id' => $application->id,
    //             'method' => $validated['method'],
    //         ]),
    //     ]);

    //     $application->update([
    //         'payment_status' => 'initiated',
    //         'submitted' => true,
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'method' => $validated['method'],
    //         'method_name' => $methodName,
    //         'application_id' => $application->id,
    //         'url' => $paymentUrl,
    //     ]);
    // }

    public function callback(Request $request)
    {
        $application = JobApplication::find($request->query('application_id'));

        if ($application) {
            $application->update([
                'payment_status' => $request->query('payment_status', 'unknown'),
            ]);
        }

        return redirect()->route('home')->with('success', true)->with('payment_status', $request->query('payment_status', 'unknown'));
    }

    /**
     * Remove the specified job application
     */
    public function destroy($id)
    {
        $application = JobApplication::findOrFail($id);
        $application->delete();

        return back()->with([
            'success' => true,
            'message' => 'আবেদন সফলভাবে মুছে ফেলা হয়েছে!'
        ]);
    }
}
