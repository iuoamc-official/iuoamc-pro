<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\AccountRecordAccess;
use App\Services\AuditTrail;
use App\Services\SensoryLearningContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

final class LearningInvitationController extends Controller
{
    public function store(Request $request, string $locale): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $request->merge(['email' => AccountRecordAccess::normalizeEmail($request->input('email'))]);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254']]);
        $code = 'IUOAMC-'.strtoupper(bin2hex(random_bytes(10)));
        DB::transaction(function () use ($request, $data, $code): void {
            DB::table('learning_invitations')->where('email', $data['email'])->where('module_id', SensoryLearningContent::MODULE_ID)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now(), 'updated_at' => now()]);
            $id = DB::table('learning_invitations')->insertGetId([
                'email' => $data['email'], 'module_id' => SensoryLearningContent::MODULE_ID,
                'code_hash' => hash('sha256', $code), 'expires_at' => now()->addDays(30),
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            AuditTrail::record('learning.code_created', null, [], ['invitation_id' => $id]);
        });

        return redirect()->route('learning.control.index', ['locale' => $locale])
            ->with('learning_invitation', ['email' => $data['email'], 'code' => $code]);
    }

    public function destroy(Request $request, string $locale, int $invitation): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        DB::transaction(function () use ($invitation): void {
            $record = DB::table('learning_invitations')->where('id', $invitation)
                ->where('module_id', SensoryLearningContent::MODULE_ID)->whereNull('archived_at')->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->used_at, 409);
            DB::table('learning_invitations')->where('id', $record->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            AuditTrail::record('learning.code_revoked', null, [], ['invitation_id' => $record->id]);
        });

        return redirect()->route('learning.control.index', ['locale' => $locale])->with('success', __('learning.code_revoked'));
    }

    public function edit(Request $request, string $locale, int $invitation): Response
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $record = DB::table('learning_invitations')->where('id', $invitation)
            ->where('module_id', SensoryLearningContent::MODULE_ID)->whereNull('archived_at')->first();
        abort_unless($record, 404);

        return response()->view('control.learning.manage', [
            'email' => $record->email, 'isEnrollment' => false,
            'deleteUrl' => route('learning.control.invitations.archive', ['locale' => $locale, 'invitation' => $invitation]),
            'editUrl' => $record->used_at ? null : route('learning.control.invitations.update', ['locale' => $locale, 'invitation' => $invitation]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function update(Request $request, string $locale, int $invitation): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $request->merge(['email' => AccountRecordAccess::normalizeEmail($request->input('email'))]);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:254'], 'confirmation_email' => ['required', 'string', 'max:254']]);
        $code = 'IUOAMC-'.strtoupper(bin2hex(random_bytes(10)));
        DB::transaction(function () use ($data, $code, $invitation): void {
            $record = DB::table('learning_invitations')->where('id', $invitation)
                ->where('module_id', SensoryLearningContent::MODULE_ID)->whereNull('archived_at')->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->used_at, 409);
            abort_unless(hash_equals($record->email, $data['confirmation_email']), 422, __('learning.confirm_email_mismatch'));
            DB::table('learning_invitations')->where('email', $data['email'])->where('module_id', SensoryLearningContent::MODULE_ID)
                ->where('id', '!=', $record->id)->whereNull('used_at')->whereNull('revoked_at')
                ->update(['revoked_at' => now(), 'updated_at' => now()]);
            DB::table('learning_invitations')->where('id', $record->id)->update([
                'email' => $data['email'], 'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addDays(30), 'revoked_at' => null, 'updated_at' => now(),
            ]);
            AuditTrail::record('learning.code_updated', null, [], ['invitation_id' => $record->id]);
        });

        return redirect()->route('learning.control.index', ['locale' => $locale])
            ->with('learning_invitation', ['email' => $data['email'], 'code' => $code]);
    }

    public function archive(Request $request, string $locale, int $invitation): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super-admin'), 403);
        $data = $request->validate(['confirmation_email' => ['required', 'string', 'max:254']]);
        DB::transaction(function () use ($data, $invitation): void {
            $record = DB::table('learning_invitations')->where('id', $invitation)
                ->where('module_id', SensoryLearningContent::MODULE_ID)->whereNull('archived_at')->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_unless(hash_equals($record->email, $data['confirmation_email']), 422, __('learning.confirm_email_mismatch'));
            DB::table('learning_invitations')->where('id', $record->id)->update([
                'archived_at' => now(), 'revoked_at' => now(), 'updated_at' => now(),
            ]);
            AuditTrail::record('learning.code_archived', null, [], ['invitation_id' => $record->id]);
        });

        return redirect()->route('learning.control.index', ['locale' => $locale])->with('success', __('learning.invitation_archived'));
    }
}
