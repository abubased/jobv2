<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFormApiCredential
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');

        if (! is_string($origin) || $origin === '') {
            return $next($request);
        }

        $allowedOrigins = array_values(array_filter((array) config('cors.allowed_origins', [])));

        if (! in_array(rtrim($origin, '/'), array_map(static fn (string $allowed): string => rtrim($allowed, '/'), $allowedOrigins), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Allowed origin required.',
            ], 403);
        }

        $publicKey = (string) config('forms.public_client_key', '');

        if ($publicKey === ''
            || $request->headers->get('X-API-CLIENT-CREDENTIAL') !== $publicKey) {
            return response()->json([
                'success' => false,
                'message' => 'Valid public API credential required.',
            ], 401);
        }

        return $next($request);
    }
}
