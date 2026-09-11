<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureControlAccess;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->appendToGroup('web', [SecurityHeaders::class]);

        $middleware->alias([
            'locale' => SetLocale::class,
            'active' => EnsureActiveUser::class,
            'control.access' => EnsureControlAccess::class,
            'password.changed' => EnsurePasswordChanged::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): string {
            $locale = $request->segment(1);
            $locale = in_array($locale, ['ar', 'en', 'fr'], true) ? $locale : 'ar';

            return route('login', ['locale' => $locale]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Central exception reporting will be expanded in the observability phase.
    })->create();
