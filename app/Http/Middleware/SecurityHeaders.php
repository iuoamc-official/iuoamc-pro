<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $cspNonce = base64_encode(random_bytes(18));
        View::share('cspNonce', $cspNonce);
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self'; " .
            "script-src 'self' 'nonce-{$cspNonce}'; font-src 'self'; connect-src 'self'; " .
            "frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        );

        // IUOAMC_ENTITY_TAX_NO_STORE_1_0_0
        if ($request->routeIs('organizations.edit')) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }
        // IUOAMC_MEMBERSHIP_NO_STORE_1_0_0
        if ($request->routeIs('memberships.*')) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }
        if ($request->routeIs('public-content.*')) {
            $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
        // IUOAMC_PRO_CERTIFICATE_HEADERS_1_0_0
        if ($request->routeIs('certificates.*', 'pro-certificates.*')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
        }
        if ($request->routeIs('pro-certificates.*')) {
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
            $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self'; img-src 'self'; font-src 'self'; script-src 'none'; connect-src 'none'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
        }
        // IUOAMC_CERTIFICATE_DATA_FORM_1_0_0
        if ($request->is('*/certificate-data/*', '*/control/certificates/data-confirmations', '*/control/certificates/data-confirmations/*')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
        return $response;
    }
}
