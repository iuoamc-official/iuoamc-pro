<?php

declare(strict_types=1);

namespace App\Http\Controllers\Control;

use App\Http\Controllers\Controller;
use App\Models\JournalArticle;
use App\Models\JournalReview;
use App\Models\Role;
use App\Services\AuditTrail;
use App\Services\JournalNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class JournalReviewController extends Controller
{
    public function __construct(private readonly JournalNotificationService $notifications) {}

    public function store(Request $request, string $locale, JournalArticle $article): RedirectResponse
    {
        abort_unless($request->user()->canDo('journal.manage'), 403);
        abort_unless($article->type === 'peer_reviewed_research' && $article->status === 'under_review', 409);
        $reviewerRoleId = Role::query()->where('slug', 'journal-reviewer')->value('id');
        $validated = $request->validate([
            'reviewer_id' => [
                'required', 'integer', Rule::exists('users', 'id')->where('status', 'active'),
                Rule::exists('role_user', 'user_id')->where('role_id', $reviewerRoleId),
                Rule::unique('journal_reviews', 'reviewer_id')->where(fn ($query) => $query->where('journal_article_id', $article->id)->where('round', $request->integer('round'))),
            ],
            'round' => ['required', 'integer', 'min:1', 'max:20'],
            'due_at' => ['nullable', 'date', 'after:today'],
        ]);

        DB::transaction(function () use ($request, $validated, $article, $locale): void {
            $review = JournalReview::query()->create([
                'journal_article_id' => $article->id,
                'reviewer_id' => $validated['reviewer_id'],
                'round' => $validated['round'],
                'status' => 'invited',
                'due_at' => $validated['due_at'] ?? null,
                'assigned_by' => $request->user()->id,
            ]);
            AuditTrail::record('journal.review.assigned', $article, [], [
                'review_id' => $review->id,
                'round' => $review->round,
            ]);
            $reviewer = $review->reviewer()->firstOrFail();
            $reviewerLocale = in_array($reviewer->preferred_locale, ['ar', 'en', 'fr'], true) ? $reviewer->preferred_locale : $locale;
            $this->notifications->queue($article->journal, 'review_assigned', $reviewer->email, $reviewerLocale, [
                'name' => $reviewer->name,
                'code' => $article->article_code,
                'title' => $article->translation($reviewerLocale)?->title ?? $article->article_code,
                'round' => $review->round,
                'due' => $review->due_at?->toDateString() ?? trans('journal.not_assigned', [], $reviewerLocale),
                'workspace_url' => route('journal.control.articles.show', ['locale' => $reviewerLocale, 'article' => $article]),
            ], $review);
        }, 5);

        return back()->with('success', trans('journal.messages.review_assigned'));
    }

    public function update(Request $request, string $locale, JournalReview $review): RedirectResponse
    {
        abort_unless($review->reviewer_id === $request->user()->id, 403);
        abort_unless($review->status === 'in_progress', 409);
        $validated = $request->validate([
            'recommendation' => ['required', Rule::in(['accept', 'minor_revision', 'major_revision', 'reject'])],
            'author_comments' => ['required', 'string', 'max:20000'],
            'confidential_comments' => ['nullable', 'string', 'max:20000'],
        ]);

        DB::transaction(function () use ($request, $review, $validated): void {
            $review->update([
                'recommendation' => $validated['recommendation'],
                'author_comments' => $validated['author_comments'],
                'confidential_comments' => $validated['confidential_comments'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now()->utc()->startOfSecond(),
            ]);
            AuditTrail::record('journal.review.submitted', $review->article, [], [
                'review_id' => $review->id,
                'round' => $review->round,
                'recommendation' => $review->recommendation,
            ], [], $request->user()->id);
            $article = $review->article()->with(['journal', 'translations'])->firstOrFail();
            $contactEmail = trim((string) $article->journal->setting('contact_email'));
            if ($contactEmail !== '') {
                $this->notifications->queue($article->journal, 'review_completed', $contactEmail, 'en', [
                    'name' => 'Editorial Office',
                    'code' => $article->article_code,
                    'title' => $article->translation('en')?->title ?? $article->article_code,
                    'round' => $review->round,
                    'workspace_url' => route('journal.control.articles.show', ['locale' => 'en', 'article' => $article]),
                ], $review);
            }
        });

        return back()->with('success', trans('journal.messages.review_submitted'));
    }

    public function respond(Request $request, string $locale, JournalReview $review): RedirectResponse
    {
        abort_unless($review->reviewer_id === $request->user()->id, 403);
        abort_unless($review->status === 'invited', 409);
        $validated = $request->validate([
            'response' => ['required', Rule::in(['accept', 'decline'])],
            'decline_reason' => [Rule::requiredIf($request->input('response') === 'decline'), 'nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $review, $validated): void {
            $review->update([
                'status' => $validated['response'] === 'accept' ? 'in_progress' : 'declined',
                'responded_at' => now()->utc()->startOfSecond(),
                'decline_reason' => $validated['response'] === 'decline' ? trim((string) $validated['decline_reason']) : null,
            ]);
            AuditTrail::record('journal.review.'.$validated['response'].'ed', $review->article, ['status' => 'invited'], [
                'review_id' => $review->id,
                'status' => $review->status,
            ], [], $request->user()->id);
        }, 5);

        return back()->with('success', trans('journal.messages.review_response_recorded'));
    }
}
