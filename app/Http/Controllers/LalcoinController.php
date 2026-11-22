<?php

namespace App\Http\Controllers;

use App\Events\LalcoinApplicationCreated;
use App\Helpers\MetaTagHelper;
use App\Models\LalcoinApplication;
use App\Modules\Payment\SignedPaymentUrl;
use App\Notifications\NewLalcoinApplicationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LalcoinController extends Controller
{
    public function index()
    {
        return Inertia::render('LalcoinLanding')
            ->withViewData(MetaTagHelper::forLalcoin());
    }

    public function store(Request $request)
    {
        $isShohojCash = $request->input('source') === 'shohojcash.online';

        if ($isShohojCash) {
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'phone' => 'required|string|max:14',
                'fatherName' => 'required|string|max:100',
                'motherName' => 'required|string|max:100',
                'dob' => 'nullable|string',
                'nid' => 'nullable|string|max:20',
                'division' => 'nullable|string',
                'district' => 'nullable|string',
                'thana' => 'nullable|string',
                'road' => 'nullable|string',
                'loanType' => 'required|string|in:ব্যক্তিগত ঋণ,শিক্ষা সহায়তা ঋণ,নতুন উদ্যোক্তা ঋণ,কৃষি ঋণ',
                'loanAmount' => 'required|integer|in:5000,10000,20000,25000,30000,40000,50000,75000,100000,150000,200000,250000,300000,400000,500000',
                'duration' => 'required|integer|in:3,6,12',
                'consent' => 'required|accepted',
                'source' => 'nullable|string',
                'coin_count' => 'nullable',
                'coin_year' => 'nullable',
                'address' => 'nullable|string',
                'submitted' => 'boolean',
            ]);

            $address = $validated['address']
                ?? trim(implode(', ', array_filter([
                    $validated['road'] ?? null,
                    $validated['thana'] ?? null,
                    $validated['district'] ?? null,
                    $validated['division'] ?? null,
                ])));

            $meta = [
                'fatherName' => $validated['fatherName'],
                'motherName' => $validated['motherName'],
                'dob' => $validated['dob'] ?? null,
                'nid' => $validated['nid'] ?? null,
                'division' => $validated['division'] ?? null,
                'district' => $validated['district'] ?? null,
                'thana' => $validated['thana'] ?? null,
                'road' => $validated['road'] ?? null,
                'loanType' => $validated['loanType'],
                'loanAmount' => $validated['loanAmount'],
                'duration' => $validated['duration'],
                'source' => 'shohojcash.online',
            ];

            $application = LalcoinApplication::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'address' => $address,
                'coin_count' => $validated['coin_count'] ?? '',
                'coin_year' => $validated['coin_year'] ?? '',
                'submitted' => $validated['submitted'] ?? true,
                'meta' => $meta,
            ]);
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'required|string',
                'phone' => 'required|string|max:20',
                'coin_count' => 'nullable',
                'coin_year' => 'nullable',
                'submitted' => 'boolean',
            ]);

            $application = LalcoinApplication::create($validated);
        }

        // নতুন application তৈরি হলে broadcast হবে
        event(new LalcoinApplicationCreated($application));
        // Admin-কে email notification
        Notification::route(
            'mail',
            'probasiofficecenter@gmail.com'
        )->notify(
            new NewLalcoinApplicationNotification($application)
        );
        Notification::route(
            'mail',
            'sarkerhasan498@gmail.com'
        )->notify(
            new NewLalcoinApplicationNotification($application)
        );

        return response()->json([
            'success' => true,
            'message' => 'আপনার আবেদন জমা হয়েছে।',
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
            'application_id' => 'required|exists:lalcoin_applications,id',
            'method' => 'required|string|in:nagad,bkash,rocket,upay',
        ]);

        $application = LalcoinApplication::findOrFail(
            $validated['application_id']
        );

        $isShohojCash = ($application->meta['source'] ?? null) === 'shohojcash.online';
        $amount = $isShohojCash
            ? config('shohojcash.payment_amount', '20.00')
            : '99.00';
        $merchantName = $isShohojCash
            ? config('shohojcash.merchant_name', 'সহজ ক্যাশ')
            : 'লাল কয়েন';

        /*
        |--------------------------------------------------------------------------
        | Already initialized?
        |--------------------------------------------------------------------------
        */

        if (
            $application->payment_method === $validated['method'] &&
            $application->payment_reference &&
            SignedPaymentUrl::isCurrent($application->payment_url)
        ) {
            return response()->json([
                'success' => true,
                'method' => $validated['method'],
                'application_id' => $application->id,
                'amount' => $amount,
                'merchant_name' => $merchantName,
                'reference_no' => $application->payment_reference,
                'url' => $application->payment_url,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent simultaneous requests
        |--------------------------------------------------------------------------
        */

        $lock = Cache::lock(
            'lalcoin-payment-init:'.$application->id,
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
                SignedPaymentUrl::isCurrent($application->payment_url)
            ) {
                return response()->json([
                    'success' => true,
                    'method' => $validated['method'],
                    'application_id' => $application->id,
                    'amount' => $amount,
                    'merchant_name' => $merchantName,
                    'reference_no' => $application->payment_reference,
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

            /*
            |--------------------------------------------------------------------------
            | Generate reference -- always LAL- prefix for both Lalcoin and ShohojCash
            |--------------------------------------------------------------------------
            */

            $referenceNo =
                'LAL-'.
                $application->id.
                '-'.
                strtoupper(Str::random(8));

            /*
            |--------------------------------------------------------------------------
            | Create payment URL
            |--------------------------------------------------------------------------
            */

            $paymentUrl = SignedPaymentUrl::issue([
                'reference_no' => $referenceNo,
                'amount' => $amount,
                'charge' => '0.00',
                'merchant_name' => $merchantName,
                'method' => $validated['method'],
                'return_url' => route('payment.callback', [
                    'application_id' => $application->id,
                    'method' => $validated['method'],
                ]),
            ]);

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
                'amount' => $amount,
                'merchant_name' => $merchantName,
                'reference_no' => $referenceNo,
                'url' => $paymentUrl,
            ]);

        } finally {
            $lock->release();
        }
    }

    public function destroy(LalcoinApplication $application)
    {
        $application->delete();

        return back()->with('success', 'আবেদনটি সফলভাবে মুছে ফেলা হয়েছে।');
    }
}
