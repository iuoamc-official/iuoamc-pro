<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\JournalEditorialMessage;
use App\Models\JournalSubmission;
use App\Services\AuditTrail;
use App\Services\JournalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class JournalMessageController extends Controller
{
    public function store(Request $request, string $locale, JournalSubmission $submission, JournalNotificationService $notifications): RedirectResponse
    {
        abort_unless($submission->accountLink()->exists(), 409);
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        DB::transaction(function () use ($request, $submission, $validated, $notifications, $locale): void {
            $message = JournalEditorialMessage::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'journal_submission_id' => $submission->id,
                'sender_id' => $request->user()->id,
                'sender_role' => 'editor',
                'body' => trim($validated['body']),
                'sent_at' => now()->utc()->startOfSecond(),
            ]);
            AuditTrail::record('journal.correspondence.editor_message_sent', $message, [], [
                'submission_code' => $submission->submission_code,
            ], [], $request->user()->id);
            $notifications->queue($submission->journal, 'editor_message_received', $submission->author_email, $submission->primary_locale, [
                'name' => $submission->author_name,
                'code' => $submission->submission_code,
                'title' => $submission->title,
                'workspace_url' => route('account.journal.submissions.show', ['locale' => $submission->primary_locale ?: $locale, 'submission' => $submission]),
            ], $message);
        }, 5);

        return back()->with('success', trans('journal.editorial_message_sent'));
    }
}
