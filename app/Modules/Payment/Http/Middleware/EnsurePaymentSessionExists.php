<?php

namespace App\Modules\Payment\Http\Middleware;

use App\Modules\Payment\Services\PaymentSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks direct access to step 2/3 URLs when a payment flow was never
 * started via the signed entry route.
 */
class EnsurePaymentSessionExists
{
    public function __construct(private PaymentSession $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->session->exists()) {
            abort(419, 'Your payment session has expired. Please start again.');
        }

        return $next($request);
    }
}
