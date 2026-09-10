<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProCertificate;
use App\Models\ProCertificateStudent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class ProCertificateStudentArchive
{
    /**
     * Return aggregate inventory only. Student names never leave this service.
     *
     * @return array{source_records:int, unique_students:int, statuses:array<string,int>}
     */
    public function inventory(): array
    {
        $records = ProCertificate::query()
            ->select(['id', 'organization_id', 'recipient_name', 'status'])
            ->orderBy('id')
            ->get();

        $groups = $this->group($records);

        return [
            'source_records' => $records->count(),
            'unique_students' => $groups->count(),
            'statuses' => $records
                ->countBy(static fn (ProCertificate $record): string => (string) $record->status)
                ->sortKeys()
                ->all(),
        ];
    }

    /**
     * Preserve exact private names using Laravel encrypted casts.
     * The return value contains counts only.
     *
     * @return array{source_records:int, archived_students:int, already_archived:bool}
     */
    public function archive(int $expectedStudents): array
    {
        if ($expectedStudents < 1) {
            throw new RuntimeException('CERTIFICATE_STUDENT_EXPECTATION_INVALID');
        }

        return DB::transaction(function () use ($expectedStudents): array {
            $records = ProCertificate::query()
                ->select(['id', 'organization_id', 'recipient_name'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $groups = $this->group($records);

            if ($groups->count() !== $expectedStudents) {
                throw new RuntimeException(sprintf(
                    'CERTIFICATE_STUDENT_COUNT_MISMATCH expected=%d actual=%d',
                    $expectedStudents,
                    $groups->count()
                ));
            }

            $existing = ProCertificateStudent::query()
                ->orderBy('organization_id')
                ->orderBy('name_fingerprint')
                ->get(['organization_id', 'name_fingerprint']);

            $expectedKeys = $groups->keys()->sort()->values()->all();
            $existingKeys = $existing
                ->map(static fn (ProCertificateStudent $student): string =>
                    $student->organization_id.':'.$student->name_fingerprint
                )
                ->sort()
                ->values()
                ->all();

            if ($existing->isNotEmpty()) {
                if ($expectedKeys !== $existingKeys) {
                    throw new RuntimeException('CERTIFICATE_STUDENT_ARCHIVE_CONFLICT');
                }

                return [
                    'source_records' => $records->count(),
                    'archived_students' => $existing->count(),
                    'already_archived' => true,
                ];
            }

            $time = now()->utc()->startOfSecond();

            foreach ($groups as $group) {
                ProCertificateStudent::query()->create([
                    'record_uuid' => (string) Str::uuid(),
                    'organization_id' => $group['organization_id'],
                    'private_name' => $group['private_name'],
                    'name_fingerprint' => $group['name_fingerprint'],
                    'source_record_count' => count($group['source_ids']),
                    'first_source_record_id' => min($group['source_ids']),
                    'last_source_record_id' => max($group['source_ids']),
                    'archived_at' => $time,
                    'created_at' => $time,
                ]);
            }

            if (ProCertificateStudent::query()->count() !== $expectedStudents) {
                throw new RuntimeException('CERTIFICATE_STUDENT_ARCHIVE_WRITE_MISMATCH');
            }

            return [
                'source_records' => $records->count(),
                'archived_students' => $expectedStudents,
                'already_archived' => false,
            ];
        }, 3);
    }

    /**
     * @param Collection<int, ProCertificate> $records
     * @return Collection<string, array{
     *   organization_id:int,
     *   private_name:string,
     *   name_fingerprint:string,
     *   source_ids:list<int>
     * }>
     */
    private function group(Collection $records): Collection
    {
        $groups = [];

        foreach ($records as $record) {
            $name = trim((string) $record->recipient_name);

            if ($name === '' || preg_match('//u', $name) !== 1) {
                throw new RuntimeException('CERTIFICATE_STUDENT_NAME_INVALID');
            }

            $fingerprint = $this->fingerprint($name);
            $key = (int) $record->organization_id.':'.$fingerprint;

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'organization_id' => (int) $record->organization_id,
                    'private_name' => $name,
                    'name_fingerprint' => $fingerprint,
                    'source_ids' => [],
                ];
            }

            $groups[$key]['source_ids'][] = (int) $record->id;
        }

        return collect($groups);
    }

    private function fingerprint(string $name): string
    {
        $normalized = class_exists(\Normalizer::class)
            ? \Normalizer::normalize($name, \Normalizer::FORM_KC)
            : $name;

        if (! is_string($normalized)) {
            throw new RuntimeException('CERTIFICATE_STUDENT_NAME_NORMALIZATION_FAILED');
        }

        $normalized = mb_strtolower(Str::squish($normalized), 'UTF-8');
        $key = (string) config('app.key');

        if ($key === '') {
            throw new RuntimeException('CERTIFICATE_STUDENT_FINGERPRINT_KEY_UNAVAILABLE');
        }

        return hash_hmac('sha256', $normalized, $key);
    }
}
