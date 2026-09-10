<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->route('locale', 'ar');

        abort_unless(in_array($locale, ['ar', 'en', 'fr'], true), 404);

        app()->setLocale($locale);

        return $next($request);
    }
}
