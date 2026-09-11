<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProCertificate;
use App\Models\ProCertificateBatch;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds operational certificate collections without mutating signed batch membership.
 */
final class ProCertificateCollection
{
    public function __construct(
        private readonly ProCertificateBatchRegistry $batchRegistry,
        private readonly ProCertificateRegistry $certificateRegistry,
        private readonly ProCertificateWorkspace $workspace,
    ) {}

    /**
     * @return Collection<int, array{
     *     key: string,
     *     organization_id: int,
     *     catalog_type_id: int,
     *     language: string,
     *     program_title: string,
     *     achievement_date: string,
     *     organization_name: string,
     *     batches: Collection<int, ProCertificateBatch>,
     *     members: Collection<int, ProCertificate>
     * }>
     */
    public function all(User $actor): Collection
    {
        $batches = $this->batchRegistry->query($actor)
            ->with('organization')
            ->orderByDesc('id')
            ->get();
        $currentCertificates = $this->workspace->partition(
            $this->certificateRegistry->query($actor)
                ->with('organization')
                ->latest('id')
                ->get()
        )['current']
            ->reject(fn (ProCertificate $certificate): bool => app(ProCertificateCorrection::class)->isSuperseded($certificate))
            ->keyBy(fn (ProCertificate $certificate): int => (int) $certificate->id);
        $currentIds = $currentCertificates->keys();
        $memberIds = $batches
            ->flatMap(fn (ProCertificateBatch $batch): array => $batch->member_ids)
            ->intersect($currentIds)
            ->unique()
            ->values();
        $collections = collect();

        foreach ($batches as $batch) {
            if (! $this->batchRegistry->verify($batch) || $this->isNonOperational($batch)) {
                continue;
            }

            $members = collect($batch->member_ids)
                ->map(fn (mixed $id): ?ProCertificate => $currentCertificates->get((int) $id))
                ->filter()
                ->values();

            if ($members->isEmpty()) {
                continue;
            }

            $identity = $this->identity($members->first());
            if ($members->contains(
                fn (ProCertificate $certificate): bool => $this->identity($certificate) !== $identity
            )) {
                continue;
            }

            $key = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
            $collection = $collections->get($key, [
                'key' => $key,
                ...$identity,
                'organization_name' => (string) ($batch->organization?->display_name
                    ?? $members->first()?->organization?->display_name
                    ?? ''),
                'batches' => collect(),
                'members' => collect(),
            ]);
            $collection['batches']->push($batch);
            $collection['members'] = $collection['members']
                ->concat($members)
                ->unique(fn (ProCertificate $certificate): int => (int) $certificate->id)
                ->sortBy(fn (ProCertificate $certificate): string => mb_strtolower((string) $certificate->public_name, 'UTF-8'))
                ->values();
            $collections->put($key, $collection);
        }

        $unbatchedGroups = $currentCertificates
            ->except($memberIds->all())
            ->filter(fn (ProCertificate $certificate): bool => $this->certificateRegistry->verify($certificate))
            ->groupBy(fn (ProCertificate $certificate): string => hash(
                'sha256',
                json_encode($this->identity($certificate), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
            ));

        foreach ($unbatchedGroups as $key => $members) {
            $existing = $collections->get($key);
            if ($existing === null && $members->count() < 2) {
                continue;
            }

            $identity = $this->identity($members->first());
            $collection = $existing ?? [
                'key' => $key,
                ...$identity,
                'organization_name' => (string) ($members->first()?->organization?->display_name ?? ''),
                'batches' => collect(),
                'members' => collect(),
            ];
            $collection['members'] = $collection['members']
                ->concat($members)
                ->unique(fn (ProCertificate $certificate): int => (int) $certificate->id)
                ->sortBy(fn (ProCertificate $certificate): string => mb_strtolower((string) $certificate->public_name, 'UTF-8'))
                ->values();
            $collections->put($key, $collection);
        }

        return $collections
            ->sortByDesc(fn (array $collection): string => $collection['achievement_date'].'|'.$collection['key'])
            ->values();
    }

    /** @return array<string, mixed> */
    public function find(User $actor, string $key): array
    {
        $collection = $this->all($actor)->firstWhere('key', $key);
        abort_unless(is_array($collection), 404);

        return $collection;
    }

    /** @return array<string, mixed>|null */
    public function forBatch(User $actor, ProCertificateBatch $batch): ?array
    {
        return $this->all($actor)->first(
            fn (array $collection): bool => $collection['batches']->contains(
                fn (ProCertificateBatch $candidate): bool => (int) $candidate->id === (int) $batch->id
            )
        );
    }

    /** @return array{organization_id: int, catalog_type_id: int, language: string, program_title: string, achievement_date: string} */
    private function identity(ProCertificate $certificate): array
    {
        return [
            'organization_id' => (int) $certificate->organization_id,
            'catalog_type_id' => (int) $certificate->catalog_type_id,
            'language' => (string) $certificate->language,
            'program_title' => preg_replace('/\s+/u', ' ', trim($certificate->program_title)) ?? '',
            'achievement_date' => $certificate->achievement_date?->toDateString() ?? '',
        ];
    }

    private function isNonOperational(ProCertificateBatch $batch): bool
    {
        return preg_match('/(?:visual[\s-]*qa|\btest\b|\bpreview\b)/iu', $batch->label) === 1;
    }
}
