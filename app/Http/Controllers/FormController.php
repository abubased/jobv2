<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Modules\Forms\FormDefinition;
use App\Modules\Forms\FormNotificationService;
use App\Modules\Forms\FormRegistry;
use App\Modules\Payment\SignedPaymentUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use Illuminate\Validation\ValidationException;

class FormController extends Controller
{
    public function __construct(
        private readonly FormRegistry $forms,
        private readonly FormNotificationService $notifications,
    ) {}

    public function index(): \Inertia\Response
    {
        return \Inertia\Inertia::render('AllApplications/Index', [
            'applications' => Application::query()
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $slug = $request->input('form_slug', $request->input('slug'));

        if (! is_string($slug) || $slug === '') {
            return response()->json([
                'success' => false,
                'message' => 'ফর্মটি পাওয়া যায়নি।',
            ], 404);
        }

        if ($request->filled('form_slug') && $request->filled('slug') && $request->input('form_slug') !== $request->input('slug')) {
            throw ValidationException::withMessages([
                'form_slug' => 'ফর্ম শনাক্তকারী মিলছে না।',
            ]);
        }

        $form = $this->forms->find($slug);

        if (! $form instanceof FormDefinition) {
            return response()->json([
                'success' => false,
                'message' => 'ফর্মটি পাওয়া যায়নি।',
            ], 404);
        }

        $request->merge([
            'slug' => $form->slug(),
            'form_slug' => $form->slug(),
        ]);

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
            'payment_amount' => $form->paymentEnabled() ? $form->resolvePaymentAmount($formData) : null,
            'merchant_name' => $form->merchantName(),
            'submitted' => true,
        ]);

        $referenceNo = $this->referenceNumber($form, $application->id);
        $application->update(['payment_reference' => $referenceNo]);
        $this->rememberApplication($request, $application);

        try {
            event(new ApplicationCreated($application));
        } catch (Throwable $exception) {
            Log::warning('Application realtime notification failed.', [
                'application_id' => $application->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->notifications->notifySubmission($form, $application);

        return response()->json([
            'success' => true,
            'message' => 'আপনার আবেদন জমা হয়েছে।',
            'application_id' => $application->id,
            'reference_no' => $referenceNo,
            'payment_enabled' => $form->paymentEnabled(),
        ]);
    }

    public function initPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'application_id' => ['required', 'integer', 'exists:all_applications,id'],
            'method' => ['required', 'string'],
        ]);

        $application = Application::query()->findOrFail($validated['application_id']);
        $this->authorizeApplication($request, $application);
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

        if ($this->paymentAlreadyCompleted($application)) {
            return response()->json([
                'success' => false,
                'message' => 'এই আবেদনের পেমেন্ট ইতিমধ্যে সম্পন্ন হয়েছে।',
            ], 422);
        }

        $amount = $form->resolvePaymentAmount($application->form_data ?? []);
        $merchantName = $form->merchantName();

        $lock = Cache::lock('universal-payment-init:'.$application->id, 30);

        if (! $lock->get()) {
            return response()->json([
                'success' => false,
                'message' => 'পেমেন্ট প্রস্তুত হচ্ছে। অনুগ্রহ করে কিছুক্ষণ অপেক্ষা করুন।',
            ], 409);
        }

        try {
            $application->refresh();

            if ($this->paymentAlreadyCompleted($application)) {
                return response()->json([
                    'success' => false,
                    'message' => 'এই আবেদনের পেমেন্ট ইতিমধ্যে সম্পন্ন হয়েছে।',
                ], 422);
            }

            if (
                $application->payment_method === $validated['method']
                && SignedPaymentUrl::isCurrent($application->payment_url)
            ) {
                return $this->paymentPayload($application, $form, $amount, $merchantName);
            }

            $referenceNo = $application->payment_reference
                ?: $this->referenceNumber($form, $application->id);

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

    private function rememberApplication(Request $request, Application $application): void
    {
        $ids = array_map('intval', (array) $request->session()->get('universal_form_application_ids', []));
        $ids[] = $application->id;

        $request->session()->put('universal_form_application_ids', array_values(array_unique($ids)));
    }

    private function authorizeApplication(Request $request, Application $application): void
    {
        $ids = array_map('intval', (array) $request->session()->get('universal_form_application_ids', []));

        if (! in_array($application->id, $ids, true)) {
            abort(403);
        }
    }

    private function paymentAlreadyCompleted(Application $application): bool
    {
        return in_array($application->payment_status, ['paid', 'success'], true);
    }

    private function referenceNumber(FormDefinition $form, int $id): string
    {
        do {
            $reference = $form->referencePrefix().'-'.$id.'-'.strtoupper(Str::random(8));
        } while (Application::query()->where('payment_reference', $reference)->exists());

        return $reference;
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
            'payment_status' => $application->payment_status,
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
