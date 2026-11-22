<?php

declare(strict_types=1);

namespace App\Modules\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class SignedPaymentUrl
{
    /** 48 hours. Accessing or reusing a URL does not extend this. */
    public const VALID_MINUTES = 2880;

    public const ROUTE = 'payment.start';

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function issue(array $parameters): string
    {
        return URL::signedRoute(self::ROUTE, $parameters, now()->addMinutes(self::VALID_MINUTES));
    }

    public static function isCurrent(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        return Request::create($url)->hasValidSignature();
    }
}
