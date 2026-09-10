<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LegacyCertificateAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        abort_unless($actor && $actor->isActive() && $actor->hasRole('super-admin'), 403);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
