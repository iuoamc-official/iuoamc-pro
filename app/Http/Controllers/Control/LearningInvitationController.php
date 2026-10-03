<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Services\AccountRecordAccess;
use App\Services\AuditTrail;
use App\Services\SensoryLearningContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                ->where('module_id', SensoryLearningContent::MODULE_ID)->lockForUpdate()->first();
            abort_unless($record, 404);
            abort_if($record->used_at, 409);
            DB::table('learning_invitations')->where('id', $record->id)->update(['revoked_at' => now(), 'updated_at' => now()]);
            AuditTrail::record('learning.code_revoked', null, [], ['invitation_id' => $record->id]);
        });

        return redirect()->route('learning.control.index', ['locale' => $locale])->with('success', __('learning.code_revoked'));
    }
}
