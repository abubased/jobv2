<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\ApplicationCreated;
use App\Events\ApplicationDashboardUpdated;
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

    public function index(Request $request): \Inertia\Response
    {
        $filters = $this->listFilters($request);

        $applications = $this->filteredApplications($filters)
            ->dashboardOrder()
            ->paginate(20)
            ->through(fn (Application $application): array => $this->dashboardApplicationData($application))
            ->withQueryString();

        return \Inertia\Inertia::render('AllApplications/Index', [
            'applications' => $applications,
            'filters' => $filters,
            'forms' => collect($this->forms->all())
                ->map(fn (FormDefinition $form): array => [
                    'slug' => $form->slug(),
                    'name' => $form->formName(),
                    'favicon' => $form->favicon(),
                ])
                ->values(),
        ]);
    }

    /**
     * Return the minimum application details required by the admin dashboard modal.
     *
     * The route is protected by the existing auth + admin middleware. Sensitive
     * payment and form payloads are deliberately not added to the paginated list.
     */
    public function details(Application $application): JsonResponse
    {
        return response()->json([
            'application' => [
                'id' => $application->id,
                'form_slug' => $application->form_slug,
                'form_name' => $application->form_name,
                'name' => $application->name,
                'phone' => $application->phone,
                'address' => $application->address,
                'form_data' => is_array($application->form_data) ? $application->form_data : [],
                'form_field_sources' => is_array($application->form_field_sources)
                    ? $application->form_field_sources
                    : null,
                'payment_url' => $application->payment_url,
            ],
        ]);
    }

    public function show(Application $application): \Inertia\Response
    {
        $form = $this->forms->find($application->form_slug);

        return \Inertia\Inertia::render('AllApplications/Show', [
            'application' => [
                'id' => $application->id,
                'form_slug' => $application->form_slug,
                'form_name' => $form instanceof FormDefinition ? $form->formName() : $application->form_name,
                'reference_no' => $application->payment_reference,
                'name' => $application->name,
                'phone' => $application->phone,
                'address' => $application->address,
                'submitted' => $application->submitted,
                'submitted_at' => $application->created_at?->toIso8601String(),
                'payment_status' => $application->payment_status,
                'payment_amount' => $application->payment_amount,
                'payment_method' => $application->payment_method,
                'merchant_name' => $application->merchant_name,
                'form_data' => $application->form_data ?? [],
                'field_sources' => $application->form_field_sources,
            ],
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

        $submittedFormData = $validated['form_data'] ?? [];
        $formData = $form->storedFormData($submittedFormData);
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
            'form_field_sources' => $form->fieldSources($submittedFormData),
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
        //sending mail
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

            $application->refresh();
            event(new ApplicationDashboardUpdated($application, 'payment_details_updated'));

            return $this->paymentPayload($application, $form, $amount, $merchantName);
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

    /**
     * Dashboard-specific projection. Keep this allow-listed: meta_info can also
     * contain internal PIN/OTP verification hashes which must not be exposed.
     *
     * @return array<string, mixed>
     */
    private function dashboardApplicationData(Application $application): array
    {
        return $application->toDashboardPayload();
    }

    /**
     * @return array<string, string>
     */
    private function listFilters(Request $request): array
    {
        $filters = [];

        foreach (['reference', 'name', 'phone', 'form', 'payment_status', 'submitted_from', 'submitted_to', 'meta_info'] as $key) {
            $value = trim((string) $request->query($key, ''));
            $filters[$key] = $value;
        }

        return $filters;
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function filteredApplications(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Application::query()->select([
            'id',
            'form_slug',
            'form_name',
            'name',
            'phone',
            'payment_status',
            'payment_amount',
            'payment_reference',
            'meta_info',
            'submitted',
            'created_at',
            'updated_at',
            'address',
            'form_data',
            'form_field_sources',
            'payment_method',
            'payment_url',
            'merchant_name',
            'is_blocked',
            'labels',
            'notes',
        ]);

        if ($filters['reference'] !== '') {
            $query->where('payment_reference', 'like', '%'.$this->like($filters['reference']).'%');
        }

        if ($filters['name'] !== '') {
            $query->where('name', 'like', '%'.$this->like($filters['name']).'%');
        }

        if ($filters['phone'] !== '') {
            $query->where('phone', 'like', '%'.$this->like($filters['phone']).'%');
        }

        if ($filters['form'] !== '') {
            $query->where(function ($inner) use ($filters): void {
                $inner->where('form_slug', $filters['form'])
                    ->orWhere('form_name', 'like', '%'.$this->like($filters['form']).'%');
            });
        }

        if ($filters['payment_status'] !== '') {
            $query->where('payment_status', $filters['payment_status']);
        }

        if ($filters['submitted_from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['submitted_from']);
        }

        if ($filters['submitted_to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['submitted_to']);
        }

        return $query;
    }

    private function like(string $value): string
    {
        return addcslashes($value, '%_\\');
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
