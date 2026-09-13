<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\JournalSubmission;
use App\Models\JournalSubmissionAccount;
use App\Services\AuditTrail;
use App\Services\JournalRevisionIntake;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class JournalSubmissionController extends Controller
{
    public function index(Request $request, string $locale): View
    {
        $submissions = JournalSubmission::query()
            ->whereHas('accountLink', fn ($query) => $query->where('user_id', $request->user()->id))
            ->with(['convertedArticle.translations', 'revisions'])
            ->latest('received_at')
            ->paginate(15);

        return view('account.journal.index', compact('submissions'));
    }

    public function claim(Request $request, string $locale): RedirectResponse
    {
        $validated = $request->validate([
            'submission_code' => ['required', 'string', 'max:80'],
            'tracking_token' => ['required', 'string', 'size:64'],
        ]);
        $submission = JournalSubmission::query()
            ->where('submission_code', Str::upper(trim($validated['submission_code'])))
            ->first();

        abort_unless(
            $submission !== null
            && hash_equals($submission->tracking_token_hash, $this->secureHash($validated['tracking_token']))
            && hash_equals($submission->author_email_hash, $this->secureHash(Str::lower($request->user()->email))),
            404
        );

        DB::transaction(function () use ($request, $submission): void {
            $locked = JournalSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $existing = JournalSubmissionAccount::query()->where('journal_submission_id', $locked->id)->first();
            abort_if($existing !== null && $existing->user_id !== $request->user()->id, 404);

            if ($existing === null) {
                $link = JournalSubmissionAccount::query()->create([
                    'journal_submission_id' => $locked->id,
                    'user_id' => $request->user()->id,
                    'link_method' => 'tracking_claim',
                    'linked_at' => now()->utc()->startOfSecond(),
                ]);
                AuditTrail::record('journal.submission.account_linked', $link, [], [
                    'submission_code' => $locked->submission_code,
                    'link_method' => $link->link_method,
                ], [], $request->user()->id);
            }
        }, 5);

        return redirect()->route('account.journal.submissions.show', [
            'locale' => $locale,
            'submission' => $submission,
        ])->with('success', __('account.journal_claimed'));
    }

    public function show(Request $request, string $locale, JournalSubmission $submission): View
    {
        $this->authorizeOwnership($request, $submission);
        $submission->load(['convertedArticle.translations', 'convertedArticle.decisions', 'revisions', 'contributors', 'messages.sender']);

        return view('account.journal.show', compact('submission'));
    }

    public function storeRevision(
        Request $request,
        string $locale,
        JournalSubmission $submission,
        JournalRevisionIntake $intake,
    ): RedirectResponse {
        $this->authorizeOwnership($request, $submission);
        $validated = $request->validate([
            'manuscript' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:25600'],
            'response_letter' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:10240'],
            'author_note' => ['nullable', 'string', 'max:5000'],
        ]);
        $submission->load('convertedArticle');
        abort_unless($submission->status === 'converted' && $submission->convertedArticle?->status === 'revision_required', 409);

        $intake->receive(
            $submission,
            $request->file('manuscript'),
            $request->file('response_letter'),
            $validated['author_note'] ?? null,
            'account_revision_intake',
        );

        return back()->with('success', __('journal.messages.revision_received'));
    }

    private function authorizeOwnership(Request $request, JournalSubmission $submission): void
    {
        abort_unless($submission->accountLink()->where('user_id', $request->user()->id)->exists(), 404);
    }

    private function secureHash(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }
}
