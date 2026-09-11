<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\JournalArticle;
use App\Models\JournalArticleVersion;
use App\Models\JournalEditorialDecision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JournalWorkflow
{
    public function __construct(private readonly JournalNotificationService $notifications) {}

    /** @var array<string, array<string, string>> */
    private const TRANSITIONS = [
        'draft' => ['submit' => 'submitted', 'publish_professional' => 'published'],
        'submitted' => ['screen' => 'initial_screening', 'publish_professional' => 'published'],
        'initial_screening' => ['send_review' => 'under_review', 'accept' => 'accepted', 'request_revision' => 'revision_required', 'reject' => 'rejected', 'publish_professional' => 'published'],
        'under_review' => ['request_revision' => 'revision_required', 'accept' => 'accepted', 'reject' => 'rejected', 'publish_professional' => 'published'],
        'revision_required' => ['resubmit' => 'under_review', 'reject' => 'rejected', 'publish_professional' => 'published'],
        'accepted' => ['copyedit' => 'copyediting', 'publish_professional' => 'published'],
        'copyediting' => ['typeset' => 'typesetting', 'publish_professional' => 'published'],
        'typesetting' => ['ready' => 'ready_to_publish', 'publish_professional' => 'published'],
        'ready_to_publish' => ['publish' => 'published', 'publish_professional' => 'published'],
        'published' => ['retract' => 'retracted'],
    ];

    public function transition(User $actor, int $articleId, int $lockVersion, string $action, ?string $reason = null): JournalArticle
    {
        return DB::transaction(function () use ($actor, $articleId, $lockVersion, $action, $reason): JournalArticle {
            $article = JournalArticle::query()
                ->with(['translations', 'authors'])
                ->lockForUpdate()
                ->findOrFail($articleId);

            if ($article->lock_version !== $lockVersion) {
                throw ValidationException::withMessages(['lock_version' => trans('journal.errors.conflict')]);
            }

            $nextStatus = self::TRANSITIONS[$article->status][$action] ?? null;
            if ($nextStatus === null) {
                throw ValidationException::withMessages(['action' => trans('journal.errors.transition')]);
            }

            $this->authorize($actor, $action);
            $this->assertPublicationRequirements($article, $action);

            $oldStatus = $article->status;
            $changes = [
                'status' => $nextStatus,
                'updated_by' => $actor->id,
                'lock_version' => $article->lock_version + 1,
            ];

            if ($nextStatus === 'accepted') {
                $changes['accepted_at'] = $article->accepted_at ?? now()->toDateString();
            }

            if ($nextStatus === 'published') {
                $snapshot = $this->snapshot($article);
                $hash = hash('sha256', $snapshot);
                $version = $article->version_of_record + 1;

                JournalArticleVersion::query()->create([
                    'journal_article_id' => $article->id,
                    'version' => $version,
                    'kind' => $article->correction_of_id ? 'correction' : 'version_of_record',
                    'payload' => $snapshot,
                    'payload_sha256' => $hash,
                    'reason' => $reason,
                    'created_by' => $actor->id,
                    'created_at' => now()->utc()->startOfSecond(),
                ]);

                $changes['published_at'] = now()->utc()->startOfSecond();
                $changes['version_of_record'] = $version;
                $changes['version_of_record_hash'] = $hash;
            }

            if ($nextStatus === 'retracted') {
                $changes['retracted_at'] = now()->utc()->startOfSecond();
            }

            $article->fill($changes)->save();
            $decision = match ($action) {
                'accept' => 'accepted',
                'request_revision' => 'revision_required',
                'retract' => 'retracted',
                'reject' => 'rejected',
                default => null,
            };
            if ($decision !== null) {
                JournalEditorialDecision::query()->create([
                    'journal_article_id' => $article->id,
                    'decision' => $decision,
                    'letter' => trim((string) $reason) ?: null,
                    'issued_by' => $actor->id,
                    'issued_at' => now()->utc()->startOfSecond(),
                ]);
            }

            AuditTrail::record(
                'journal.article.'.$action,
                $article,
                ['status' => $oldStatus],
                ['status' => $nextStatus],
                ['reason' => $reason, 'version_of_record' => $article->version_of_record]
            );

            $event = match ($action) {
                'accept' => 'article_accepted',
                'request_revision' => 'revision_requested',
                'reject' => 'article_rejected',
                'publish', 'publish_professional' => 'article_published',
                'retract' => 'article_retracted',
                default => null,
            };
            if ($event !== null) {
                foreach ($article->authors->filter(fn ($author): bool => (bool) $author->pivot->is_corresponding) as $author) {
                    if (trim((string) $author->email) === '') {
                        continue;
                    }
                    $locale = in_array($article->primary_locale, ['ar', 'en', 'fr'], true) ? $article->primary_locale : 'en';
                    $this->notifications->queue($article->journal, $event, $author->email, $locale, [
                        'name' => $author->name,
                        'code' => $article->article_code,
                        'title' => $article->translation($locale)?->title ?? $article->article_code,
                        'reason' => trim((string) $reason),
                        'record_url' => route('journal.public.articles.show', ['locale' => $locale, 'article' => $article->slug]),
                    ], $article);
                }
            }

            return $article->fresh(['translations', 'authors', 'issue', 'versions']);
        }, 5);
    }

    private function authorize(User $actor, string $action): void
    {
        $permission = in_array($action, ['accept', 'reject', 'publish', 'publish_professional', 'retract'], true)
            ? 'journal.publish'
            : 'journal.manage';

        abort_unless($actor->canDo($permission), 403);
    }

    private function assertPublicationRequirements(JournalArticle $article, string $action): void
    {
        if ($article->type === 'peer_reviewed_research' && $action === 'publish_professional') {
            throw ValidationException::withMessages(['action' => trans('journal.errors.research_workflow_required')]);
        }

        if ($article->type === 'professional_article' && in_array($action, [
            'submit', 'screen', 'send_review', 'request_revision', 'resubmit', 'accept', 'reject', 'copyedit', 'typeset', 'ready', 'publish',
        ], true)) {
            throw ValidationException::withMessages(['action' => trans('journal.errors.professional_direct_publish')]);
        }

        if ($action === 'accept' && $article->type === 'peer_reviewed_research') {
            $minimumReviews = max(1, (int) ($article->journal->settings['minimum_peer_reviews'] ?? 2));
            $completedReviews = $article->reviews()->where('status', 'submitted')->count();
            if ($article->status !== 'under_review' || $completedReviews < $minimumReviews) {
                throw ValidationException::withMessages([
                    'action' => trans('journal.errors.research_review_required', ['count' => $minimumReviews]),
                ]);
            }
        }

        if (! in_array($action, ['publish', 'publish_professional'], true)) {
            return;
        }

        if (
            $article->issue === null
            || $article->authors->isEmpty()
            || $article->translations->pluck('locale')->sort()->values()->all() !== ['ar', 'en', 'fr']
            || blank($article->pdf_path)
            || blank($article->pdf_sha256)
            || blank($article->wicp_registration_number)
            || $article->wicp_verified_at === null
        ) {
            throw ValidationException::withMessages(['action' => trans('journal.errors.publication_incomplete')]);
        }
    }

    private function snapshot(JournalArticle $article): string
    {
        $payload = [
            'schema' => 'iuoamc-journal-vor-1',
            'record_uuid' => $article->record_uuid,
            'article_code' => $article->article_code,
            'type' => $article->type,
            'doi' => $article->doi,
            'license' => $article->license,
            'pdf_sha256' => $article->pdf_sha256,
            'wicp_registration' => [
                'number' => $article->wicp_registration_number,
                'registered_at' => $article->wicp_registered_at?->toDateString(),
                'verification_url' => $article->wicp_verification_url,
                'verified_at' => $article->wicp_verified_at?->toIso8601String(),
                'issuer' => 'WICP',
            ],
            'issue' => $article->issue?->only(['volume', 'number', 'slug']),
            'authors' => $article->authors->map(fn ($author): array => [
                'name' => $author->name,
                'latin_name' => $author->latin_name,
                'orcid' => $author->orcid,
                'affiliation_name' => $author->pivot->affiliation_name,
                'affiliation_ror' => $author->pivot->affiliation_ror,
                'position' => $author->pivot->position,
            ])->values()->all(),
            'translations' => $article->translations->sortBy('locale')->map(fn ($translation): array => [
                'locale' => $translation->locale,
                'title' => $translation->title,
                'subtitle' => $translation->subtitle,
                'abstract' => $translation->abstract,
                'body' => $translation->body,
                'keywords' => $translation->keywords,
                'references' => $translation->references,
            ])->values()->all(),
        ];

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
