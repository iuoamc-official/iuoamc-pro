<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

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
        abort_unless($submission->accountLink()->where('user_id', $request->user()->id)->exists(), 404);
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        DB::transaction(function () use ($request, $submission, $validated, $notifications): void {
            $message = JournalEditorialMessage::query()->create([
                'record_uuid' => (string) Str::uuid(),
                'journal_submission_id' => $submission->id,
                'sender_id' => $request->user()->id,
                'sender_role' => 'author',
                'body' => trim($validated['body']),
                'sent_at' => now()->utc()->startOfSecond(),
            ]);
            AuditTrail::record('journal.correspondence.author_message_sent', $message, [], [
                'submission_code' => $submission->submission_code,
            ], [], $request->user()->id);

            $contact = trim((string) $submission->journal->setting('contact_email'));
            if ($contact !== '') {
                $notifications->queue($submission->journal, 'author_message_received', $contact, 'en', [
                    'name' => 'Editorial Office',
                    'code' => $submission->submission_code,
                    'title' => $submission->title,
                    'workspace_url' => route('journal.control.submissions.show', ['locale' => 'en', 'submission' => $submission]),
                ], $message);
            }
        }, 5);

        return back()->with('success', trans('journal.editorial_message_sent'));
    }
}
