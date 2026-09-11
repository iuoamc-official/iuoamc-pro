<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountRecordAccess;
use App\Services\AuditTrail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class VerifyEmailController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account.dashboard', ['locale' => app()->getLocale()]);
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request, AccountRecordAccess $records): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail() && $request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
            AuditTrail::record('account.email_verified', $request->user(), [], [], [], (int) $request->user()->id);
        }
        $records->sync($request->user(), true);

        return redirect()->route('account.dashboard', ['locale' => app()->getLocale()])
            ->with('success', __('account.email_verified'));
    }
}
