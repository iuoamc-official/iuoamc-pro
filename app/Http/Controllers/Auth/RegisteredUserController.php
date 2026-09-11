<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountRecordAccess;
use App\Services\AuditTrail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => AccountRecordAccess::normalizeEmail($request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'terms' => ['accepted'],
        ]);
        $locale = (string) $request->route('locale', 'ar');
        $user = User::query()->create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'password' => $data['password'],
            'status' => 'active',
            'preferred_locale' => $locale,
            'must_change_password' => false,
        ]);

        event(new Registered($user));
        Auth::login($user);
        $request->session()->regenerate();
        AuditTrail::record('account.registered', $user, [], ['user_id' => (int) $user->id], [], (int) $user->id);

        return redirect()->route('verification.notice', ['locale' => $locale]);
    }
}
