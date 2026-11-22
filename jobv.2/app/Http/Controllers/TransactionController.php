<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Apay\ApayException;
use App\Services\Apay\ApayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Example usage of ApayService from a plain Inertia controller.
 *
 * Route names (checkout.step-two, checkout.complete, checkout.cancelled) are
 * illustrative — wire up your own routes/web.php entries and Inertia pages to
 * match. Laravel resolves ApayService automatically; no binding is required.
 */
final class TransactionController extends Controller
{
    public function __construct(private readonly ApayService $apay) {}

    public function storeStepOne(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invoice_no' => ['required', 'string', 'max:64'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'customer_input' => ['required', 'string', 'max:255'],
        ]);

        try {
            $response = $this->apay->stepOne(
                invoiceNo: $validated['invoice_no'],
                merchantName: (string) config('app.name'),
                amount: (string) $validated['amount'],
                customerInput: $validated['customer_input'],
            );
        } catch (ApayException $e) {
            return back()->withErrors($e->isValidation() ? ($e->errors ?? []) : ['apay' => $e->getMessage()]);
        }

        return to_route('checkout.step-two', $response->transactionId());
    }

    public function storeStepTwo(Request $request, string $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'second_input' => ['required', 'string', 'regex:/^\d{4,5}$/'],
        ]);

        try {
            $this->apay->stepTwo($transaction, $validated['second_input']);
        } catch (ApayException $e) {
            return back()->withErrors(['apay' => $e->getMessage()]);
        }

        return to_route('checkout.step-three', $transaction);
    }

    public function storeStepThree(Request $request, string $transaction): RedirectResponse
    {
        $validated = $request->validate([
            'backup_code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        try {
            $response = $this->apay->stepThree($transaction, $validated['backup_code']);
        } catch (ApayException $e) {
            // A genuine fault (e.g. retries already exhausted, service down) —
            // not the same as a wrong code, which never throws.
            return back()->withErrors(['apay' => $e->getMessage()]);
        }

        if ($response->success) {
            return to_route('checkout.complete', $transaction);
        }

        // Wrong code, retries may remain. This is page state, not a form error.
        return back()->with('apayResult', [
            'code' => $response->code,
            'message' => $response->message,
            'retry' => $response->retry(),
        ]);
    }

    public function cancel(string $transaction): RedirectResponse
    {
        try {
            $this->apay->cancel($transaction);
        } catch (ApayException $e) {
            return back()->withErrors(['apay' => $e->getMessage()]);
        }

        return to_route('checkout.cancelled');
    }
}
