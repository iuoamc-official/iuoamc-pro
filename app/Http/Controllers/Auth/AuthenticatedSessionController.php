<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $key = Str::lower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => trans('ui.too_many_attempts', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        $remember = (bool) ($credentials['remember'] ?? false);
        unset($credentials['remember']);
        $credentials['status'] = 'active';

        if (! Auth::attempt($credentials, $remember)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'email' => trans('ui.invalid_credentials'),
            ]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $locale = (string) $request->route('locale', 'ar');
        $user->forceFill([
            'last_login_at' => now(),
            'preferred_locale' => $locale,
        ])->save();

        AuditTrail::record('auth.login', $user, [], [], [], (int) $user->id);

        return redirect()->intended(route('dashboard', ['locale' => $locale]));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            AuditTrail::record('auth.logout', $user, [], [], [], (int) $user->id);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login', [
            'locale' => $request->route('locale', 'ar'),
        ]);
    }
}
