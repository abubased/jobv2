<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Modules\Forms\FormDefinition;
use App\Modules\Forms\FormRegistry;
use App\Notifications\ApplicationSubmittedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FormController extends Controller
{
    public function __construct(private readonly FormRegistry $forms) {}

    public function show(string $slug): Response
    {
        $form = $this->forms->find($slug);

        abort_unless($form instanceof FormDefinition, 404);

        return Inertia::render('Forms/Show', [
            'form' => $form->toPublicArray(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $form = $this->forms->find((string) $request->input('slug'));

        if (! $form instanceof FormDefinition) {
            return response()->json([
                'success' => false,
                'message' => 'ফর্মটি পাওয়া যায়নি।',
            ], 404);
        }

        $this->blankStringsToNull($request, 'form_data');

        $validated = $request->validate($form->submissionRules());

        $formData = $form->storedFormData($validated['form_data'] ?? []);
        $address = $form->composeAddress($formData, $validated['address'] ?? null);

        if ($address === '') {
            throw ValidationException::withMessages([
                'address' => 'ঠিকানা প্রয়োজন।',
            ]);
        }

        $application = Application::create([
            'form_slug' => $form->slug(),
            'form_name' => $form->formName(),
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'address' => $address,
            'form_data' => $formData,
            'submitted' => true,
        ]);

        event(new ApplicationCreated($application));
        $this->notify($form, $application);

        return response()->json([
            'success' => true,
            'message' => 'আপনার আবেদন জমা হয়েছে।',
            'payment_enabled' => $form->paymentEnabled(),
            'application' => [
                'id' => $application->id,
                'form_slug' => $application->form_slug,
                'name' => $application->name,
                'phone' => $application->phone,
            ],
        ]);
    }

    public function initPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application_id' => ['required', 'integer', 'exists:all_applications,id'],
            'method' => ['required', 'string'],
        ]);

        $application = Application::query()->findOrFail($validated['application_id']);
        $form = $this->forms->find($application->form_slug);

        if (! $form instanceof FormDefinition || ! $form->paymentEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'এই ফর্মের পেমেন্ট চালু নেই।',
            ], 422);
        }

        if (! $form->acceptsMethod($validated['method'])) {
            throw ValidationException::withMessages([
                'method' => 'এই পেমেন্ট মাধ্যম গ্রহণ করা হয় না।',
            ]);
        }

        $amount = $form->resolvePaymentAmount($application->form_data ?? []);
        $merchantName = $form->merchantName();

        if (
            $application->payment_method === $validated['method']
            && $application->payment_reference
            && $application->payment_url
        ) {
            return $this->paymentPayload($application, $form, $amount, $merchantName);
        }

        $lock = Cache::lock('all-applications-payment-init:'.$application->id, 30);

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'পেমেন্ট প্রস্তুত হচ্ছে। অনুগ্রহ করে কিছুক্ষণ অপেক্ষা করুন।',
            ], 409);
        }

        try {
            $application->refresh();

            if (
                $application->payment_method === $validated['method']
                && $application->payment_reference
                && $application->payment_url
            ) {
                return $this->paymentPayload($application, $form, $amount, $merchantName);
            }

            $referenceNo = $form->referencePrefix().'-'.$application->id.'-'.strtoupper(Str::random(8));

            $paymentUrl = URL::signedRoute('apay-payment.start', [
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

            $application->update([
                'payment_method' => $validated['method'],
                'payment_reference' => $referenceNo,
                'payment_url' => $paymentUrl,
                'payment_status' => 'initiated',
                'payment_amount' => $amount,
                'merchant_name' => $merchantName,
                'submitted' => true,
            ]);

            return $this->paymentPayload($application->fresh(), $form, $amount, $merchantName);
        } finally {
            $lock->release();
        }
    }

    public function index(): Response
    {
        return Inertia::render('AllApplications/Index', [
            'applications' => Application::query()
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    private function notify(FormDefinition $form, Application $application): void
    {
        $emails = $form->notificationEmails();

        if ($emails === []) {
            return;
        }

        Notification::route('mail', $emails)->notify(new ApplicationSubmittedNotification($application));
    }

    private function paymentPayload(Application $application, FormDefinition $form, string $amount, string $merchantName): JsonResponse
    {
        return response()->json([
            'success' => true,
            'method' => $application->payment_method,
            'application_id' => $application->id,
            'amount' => $amount,
            'merchant_name' => $merchantName,
            'reference_no' => $application->payment_reference,
            'url' => $application->payment_url,
            'form_slug' => $form->slug(),
        ]);
    }

    private function blankStringsToNull(Request $request, string $key): void
    {
        $value = $request->input($key);

        if (! is_array($value)) {
            return;
        }

        $request->merge([
            $key => collect($value)->map(static fn (mixed $item): mixed => $item === '' ? null : $item)->all(),
        ]);
    }
}
