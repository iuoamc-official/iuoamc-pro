<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

final class PasswordResetLinkController extends Controller
{
    public function create(): View { return view('auth.forgot-password'); }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        Password::sendResetLink(['email' => strtolower(trim((string) $request->input('email')))]);

        return back()->with('status', __('account.reset_sent'));
    }
}
