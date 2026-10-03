<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountRecordAccess;
use App\Services\AuditTrail;
use App\Services\SensoryLearningContent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class LearningAccessController extends Controller
{
    public function __construct(private SensoryLearningContent $content) {}

    public function create(): Response
    {
        return response()->view('auth.learning-access')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, string $locale): RedirectResponse
    {
        $request->merge(['email' => AccountRecordAccess::normalizeEmail($request->input('email'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[+0-9() .-]{6,40}$/'],
            'country' => ['required', 'string', 'max:100'],
            'access_code' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string', 'max:72'],
            'terms' => ['accepted'],
        ]);
        $isNew = false;
        $user = DB::transaction(function () use ($request, $data, $locale, &$isNew): User {
            $invitation = DB::table('learning_invitations')
                ->where('code_hash', hash('sha256', strtoupper(trim($data['access_code']))))
                ->where('email', $data['email'])->where('module_id', SensoryLearningContent::MODULE_ID)
                ->lockForUpdate()->first();
            if (! $invitation || $invitation->used_at || $invitation->revoked_at || now()->gte($invitation->expires_at)) {
                throw ValidationException::withMessages(['access_code' => __('learning.invalid_code')]);
            }
            $user = User::query()->where('email', $data['email'])->lockForUpdate()->first();
            if ($user) {
                if (! $user->isActive() || ! Hash::check($data['password'], $user->password)) {
                    throw ValidationException::withMessages(['password' => __('ui.invalid_credentials')]);
                }
            } else {
                $request->validate(['password' => ['confirmed', Password::min(12)->letters()->mixedCase()->numbers()]]);
                $user = User::query()->create([
                    'name' => trim($data['name']), 'email' => $data['email'], 'password' => $data['password'],
                    'status' => 'active', 'preferred_locale' => $locale, 'must_change_password' => false,
                ]);
                $isNew = true;
            }
            $enrollment = DB::table('learning_enrollments')->where('user_id', $user->id)
                ->where('module_id', SensoryLearningContent::MODULE_ID)->first();
            if (! $enrollment) {
                $enrollmentId = DB::table('learning_enrollments')->insertGetId([
                    'user_id' => $user->id, 'module_id' => SensoryLearningContent::MODULE_ID,
                    'content_version' => $this->content->version(), 'enrolled_by' => $invitation->created_by,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } else {
                $enrollmentId = $enrollment->id;
            }
            DB::table('learning_invitations')->where('id', $invitation->id)->update([
                'used_by' => $user->id, 'used_at' => now(), 'student_name' => trim($data['name']),
                'student_phone' => $data['phone'], 'student_country' => trim($data['country']), 'updated_at' => now(),
            ]);
            AuditTrail::record('learning.code_redeemed', $user, [], [
                'invitation_id' => $invitation->id, 'enrollment_id' => $enrollmentId,
            ], [], (int) $user->id);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        if ($isNew) {
            event(new Registered($user));
        }

        return redirect()->route($user->hasVerifiedEmail() ? 'account.learning.index' : 'verification.notice', ['locale' => $locale])
            ->with('success', __('learning.activation_success'));
    }

    public function login(Request $request, string $locale): RedirectResponse
    {
        $request->merge(['email' => AccountRecordAccess::normalizeEmail($request->input('email'))]);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'password' => ['required', 'string', 'max:72']]);
        if (! Auth::attempt($data + ['status' => 'active'])) {
            throw ValidationException::withMessages(['email' => __('ui.invalid_credentials')]);
        }
        $request->session()->regenerate();
        $request->session()->forget('url.intended');
        AuditTrail::record('learning.login', $request->user(), [], [], [], (int) $request->user()->id);

        return redirect()->route('account.learning.index', ['locale' => $locale]);
    }
}
