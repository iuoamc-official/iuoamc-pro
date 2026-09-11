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
    /** @var array<string, array<string, string>> */
    private const TRANSITIONS = [
        'draft' => ['submit' => 'submitted'],
        'submitted' => ['screen' => 'initial_screening'],
        'initial_screening' => ['send_review' => 'under_review', 'accept' => 'accepted', 'request_revision' => 'revision_required'],
        'under_review' => ['request_revision' => 'revision_required', 'accept' => 'accepted'],
        'revision_required' => ['resubmit' => 'under_review'],
        'accepted' => ['copyedit' => 'copyediting'],
        'copyediting' => ['typeset' => 'typesetting'],
        'typesetting' => ['ready' => 'ready_to_publish'],
        'ready_to_publish' => ['publish' => 'published'],
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

            return $article->fresh(['translations', 'authors', 'issue', 'versions']);
        }, 5);
    }

    private function authorize(User $actor, string $action): void
    {
        $permission = in_array($action, ['accept', 'publish', 'retract'], true)
            ? 'journal.publish'
            : 'journal.manage';

        abort_unless($actor->canDo($permission), 403);
    }

    private function assertPublicationRequirements(JournalArticle $article, string $action): void
    {
        if ($action === 'accept' && $article->type === 'peer_reviewed_research') {
            $minimumReviews = max(1, (int) ($article->journal->settings['minimum_peer_reviews'] ?? 2));
            $completedReviews = $article->reviews()->where('status', 'submitted')->count();
            if ($article->status !== 'under_review' || $completedReviews < $minimumReviews) {
                throw ValidationException::withMessages([
                    'action' => trans('journal.errors.research_review_required', ['count' => $minimumReviews]),
                ]);
            }
        }

        if ($action !== 'publish') {
            return;
        }

        if ($article->issue === null || $article->authors->isEmpty() || $article->translations->pluck('locale')->sort()->values()->all() !== ['ar', 'en', 'fr']) {
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
