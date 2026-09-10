<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LegacyCertificate;
use App\Models\LegacyCertificateExclusion;
use App\Models\LegacyCertificateImport;
use App\Models\LegacyCertificatePurgeProof;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use RuntimeException;

/** Permanent removal from Next, with signed non-content exclusions against reimport. */
final class LegacyCertificatePurge
{
    public const SCHEMA = 'iuoamc-legacy-certificate-permanent-purge-v1';
    public const EVENT = 'legacy.certificates.permanently.deleted';
    public const COUNT = 353;
    public const REMAINING = 1372;
    public const GROUPS = 11;
    private const TRASH_EVENT = 'legacy.certificates.visibility.changed';
    private const RAW_COLUMNS = ['id', 'import_id', 'source_database', 'source_table', 'source_id',
        'snapshot_id', 'holder_name', 'certificate_title', 'registration_number', 'barcode', 'barcode_kind',
        'barcode_sha256', 'stored_status', 'source_country', 'valid_from', 'valid_until', 'legacy_type',
        'legacy_design', 'source_row_sha256', 'original_fields', 'sql_candidate_source_ids', 'candidate_count',
        'is_ambiguous', 'projection_hash', 'projection_hmac'];

    /** A missing old manifest must never be treated as permission for a fresh reimport. */
    public function assertNoPriorPurge(): void
    {
        $this->targetOnly();
        $this->check(! AuditLog::query()->where('event', self::EVENT)->exists(), 'FRESH_IMPORT_FORBIDDEN');
        foreach (['legacy_certificate_purge_proofs', 'legacy_certificate_exclusions'] as $table) {
            $this->check(! Schema::hasTable($table) || ! DB::table($table)->exists(), 'FRESH_IMPORT_FORBIDDEN');
        }
    }

    /** Read-only. A previous completed purge is a verified replay. */
    public function preview(): array
    {
        $this->targetOnly();
        $import = app(LegacyCertificateRegistry::class)->currentImport();
        $state = $this->reconciliation($import);
        if ($state['purged'] !== 0) { return $state + ['replay' => true]; }
        $selection = $this->selection($import);
        return ['purged' => 0, 'affected' => self::COUNT, 'remaining' => self::REMAINING,
            'local_ids' => array_column($selection['exclusions'], 'local_id'),
            'source_ids' => array_column($selection['exclusions'], 'source_id'),
            'selection_sha256' => $selection['trash']['selection_sha256'],
            'exclusions_sha256' => $this->digest($selection['exclusions']), 'replay' => false];
    }

    /**
     * Registry calls this AFTER authenticating the original import manifest.
     * Deliberately never calls currentImport(), verifyImport(), or Trash, avoiding recursion.
     */
    public function reconciliation(LegacyCertificateImport $import): array
    {
        $this->targetOnly();
        $hasProofs = Schema::hasTable('legacy_certificate_purge_proofs');
        $hasExclusions = Schema::hasTable('legacy_certificate_exclusions');
        if (! $hasProofs && ! $hasExclusions) {
            $this->check(! AuditLog::query()->where('event', self::EVENT)->exists(), 'PROOF_TABLE_MISSING');
            return $this->unpurged();
        }
        $this->check($hasProofs && $hasExclusions, 'PARTIAL_SCHEMA');
        $proofs = LegacyCertificatePurgeProof::query()->get();
        if ($proofs->isEmpty()) {
            $this->check(! LegacyCertificateExclusion::query()->exists()
                && ! AuditLog::query()->where('event', self::EVENT)->exists(), 'INCOMPLETE_PURGE_PROOF');
            return $this->unpurged();
        }
        $this->check($proofs->count() === 1, 'PROOF_COUNT');
        $this->assertGuards();
        $proof = $proofs->first();
        $p = $proof->payload;
        $this->check(is_array($p) && $proof->snapshot_id === LegacyCertificateRegistry::SNAPSHOT
            && $proof->import_id === (int) $import->id
            && ($p['schema'] ?? null) === self::SCHEMA
            && ($p['snapshot_id'] ?? null) === LegacyCertificateRegistry::SNAPSHOT
            && ($p['source_database'] ?? null) === LegacyCertificateRegistry::SOURCE_DATABASE
            && ($p['source_table'] ?? null) === LegacyCertificateRegistry::SOURCE_TABLE
            && ($p['import_id'] ?? null) === (int) $import->id
            && ($p['original_count'] ?? null) === LegacyCertificateRegistry::RECORD_COUNT
            && ($p['deleted_count'] ?? null) === self::COUNT && ($p['remaining_count'] ?? null) === self::REMAINING
            && ($p['groups'] ?? null) === self::GROUPS
            && ($p['import_payload_sha256'] ?? null) === $import->payload_sha256
            && ($p['import_manifest_hmac_sha256'] ?? null) === hash('sha256', (string) $import->manifest_hmac)
            && is_int($p['actor_id'] ?? null) && $p['actor_id'] > 0
            && is_string($p['occurred_at_utc'] ?? null)
            && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $p['occurred_at_utc']) === 1
            && hash_equals((string) $proof->payload_sha256, $this->digest($p)), 'PROOF_INTEGRITY');
        $exclusions = $this->exclusions((int) $proof->id);
        $this->check(count($exclusions) === self::COUNT
            && LegacyCertificateExclusion::query()->count() === self::COUNT
            && ($p['exclusions_sha256'] ?? null) === $this->digest($exclusions), 'EXCLUSION_INTEGRITY');
        $localIds = []; $sourceIds = [];
        foreach ($exclusions as $row) {
            $this->check($row['snapshot_id'] === LegacyCertificateRegistry::SNAPSHOT
                && $row['source_database'] === LegacyCertificateRegistry::SOURCE_DATABASE
                && $row['source_table'] === LegacyCertificateRegistry::SOURCE_TABLE
                && $row['local_id'] > 0 && preg_match('/^[1-9][0-9]{0,19}$/D', $row['source_id']) === 1
                && preg_match('/^[a-f0-9]{64}$/D', $row['source_row_sha256']) === 1, 'EXCLUSION_SCOPE');
            $localIds[] = $row['local_id']; $sourceIds[] = $row['source_id'];
        }
        $this->check(count(array_unique($localIds)) === self::COUNT
            && count(array_unique($sourceIds, SORT_STRING)) === self::COUNT, 'EXCLUSION_IDENTITIES');
        $audit = AuditLog::query()->find($proof->audit_id);
        $meta = $audit?->metadata ?? [];
        $this->check($audit !== null && $audit->event === self::EVENT && (int) $audit->actor_id === $p['actor_id']
            && ($meta['schema'] ?? null) === self::SCHEMA
            && ($meta['proof_sha256'] ?? null) === $proof->payload_sha256
            && ($meta['deleted_count'] ?? null) === self::COUNT
            && ($meta['remaining_count'] ?? null) === self::REMAINING
            && ($meta['exclusions_sha256'] ?? null) === $p['exclusions_sha256']
            && ($meta['trash_selection_sha256'] ?? null) === $p['trash_selection_sha256']
            && ($meta['scope'] ?? null) === 'already_trashed_shared_nonempty_barcodes'
            && app(IntegrityService::class)->verifyAuditLog($audit)['valid'] === true
            && AuditLog::query()->where('event', self::EVENT)->count() === 1, 'PURGE_AUDIT_BINDING');
        $trashAudit = AuditLog::query()->find($p['trash_audit_id'] ?? 0);
        $trashMeta = $trashAudit?->metadata ?? [];
        $this->check($trashAudit !== null && $trashAudit->event === self::TRASH_EVENT
            && (int) $trashAudit->id < (int) $audit->id
            && ($trashMeta['operation'] ?? null) === 'trashed'
            && ($trashMeta['affected'] ?? null) === self::COUNT
            && ($trashMeta['groups'] ?? null) === self::GROUPS
            && ($trashMeta['selection_sha256'] ?? null) === $p['trash_selection_sha256']
            && ($trashMeta['manifest_payload_sha256'] ?? null) === $p['trash_manifest_sha256']
            && app(IntegrityService::class)->verifyAuditLog($trashAudit)['valid'] === true, 'TRASH_AUDIT_BINDING');
        $this->check(! LegacyCertificate::query()->whereIn('id', $localIds)->exists()
            && ! LegacyCertificate::query()->where('source_database', LegacyCertificateRegistry::SOURCE_DATABASE)
                ->where('source_table', LegacyCertificateRegistry::SOURCE_TABLE)->whereIn('source_id', $sourceIds)->exists(),
            'PURGED_RECORD_REAPPEARED');
        $remaining = $this->rawRows();
        $this->check(count($remaining) === self::REMAINING
            && ($p['remaining_raw_rows_sha256'] ?? null) === $this->digest($remaining), 'REMAINING_BYTES_CHANGED');
        return ['purged' => self::COUNT, 'affected' => self::COUNT, 'remaining' => self::REMAINING,
            'groups' => self::GROUPS, 'local_ids' => $localIds, 'source_ids' => $sourceIds,
            'proof_sha256' => (string) $proof->payload_sha256, 'audit_id' => (int) $audit->id,
            'proof_id' => (int) $proof->id];
    }

    /** Atomic mutation. SQL bypass of the archive's model delete hook is intentional and bounded here. */
    public function apply(User $actor): array
    {
        $this->authorize($actor);
        return $this->execute($actor);
    }

    /** Real database rehearsal; the complete DML path and guard probes are always rolled back. */
    public function rehearse(User $actor): array
    {
        $this->authorize($actor);
        $this->assertGuards();
        $import = app(LegacyCertificateRegistry::class)->currentImport();
        $current = $this->reconciliation($import);
        if ($current['purged'] !== 0) { return $current + ['replay' => true, 'rehearsal' => 'already_purged_verified']; }
        $preview = $this->preview();
        $beforeRows = $this->rawRows();
        $beforeHash = $this->digest($beforeRows);
        $auditCount = AuditLog::query()->count();
        $excludedRow = (array) DB::table('legacy_certificates')->where('id', $preview['local_ids'][0])->first();
        $survivorId = DB::table('legacy_certificates')->whereNotIn('id', $preview['local_ids'])->value('id');
        $this->check($excludedRow !== [] && $survivorId !== null, 'REHEARSAL_FIXTURE');
        DB::beginTransaction();
        try {
            $result = $this->execute($actor);
            $this->check($result['purged'] === self::COUNT && $result['remaining'] === self::REMAINING, 'REHEARSAL_PURGE_RESULT');
            // Same stable source identity in a different snapshot must remain excluded.
            $excludedRow['snapshot_id'] = '20990101T000000Z-reimport-test';
            $this->expectBlocked(fn () => DB::table('legacy_certificates')->insert($excludedRow), 'REINSERT');
            $this->expectBlocked(fn () => DB::table('legacy_certificates')->where('id', $survivorId)->update([
                'source_database' => $excludedRow['source_database'], 'source_table' => $excludedRow['source_table'],
                'source_id' => $excludedRow['source_id'], 'snapshot_id' => $excludedRow['snapshot_id'],
            ]), 'IDENTITY_UPDATE');
            $this->expectBlocked(fn () => DB::table('legacy_certificate_exclusions')->where('purge_id', $result['proof_id'])->delete(), 'EXCLUSION_DELETE');
            $this->expectBlocked(fn () => DB::table('legacy_certificate_purge_proofs')->where('id', $result['proof_id'])->update(['payload_sha256' => str_repeat('0', 64)]), 'PROOF_UPDATE');
            $this->reconciliation($import);
        } finally {
            DB::rollBack();
        }
        $this->check($this->digest($this->rawRows()) === $beforeHash
            && AuditLog::query()->count() === $auditCount
            && ! LegacyCertificatePurgeProof::query()->exists() && ! LegacyCertificateExclusion::query()->exists(), 'REHEARSAL_ROLLBACK');
        $verified = app(LegacyCertificateRegistry::class)->verifyImport((int) $import->id);
        $this->check($verified['verified'] === LegacyCertificateRegistry::RECORD_COUNT, 'REHEARSAL_ORIGINALS_RESTORED');
        return ['rehearsal' => 'passed_and_rolled_back', 'purged' => 0,
            'tested_delete' => self::COUNT, 'tested_remaining' => self::REMAINING,
            'reimport_insert_blocked' => true, 'reimport_update_blocked' => true,
            'evidence_mutations_blocked' => true, 'original_rows_restored' => true];
    }

    private function expectBlocked(callable $attempt, string $label): void
    {
        try { $attempt(); }
        catch (QueryException $error) {
            $this->check(($error->errorInfo[0] ?? null) === '45000', 'REHEARSAL_UNEXPECTED_SQL_'.$label);
            return;
        }
        throw new RuntimeException('LEGACY_PURGE_REHEARSAL_GUARD_FAILED_'.$label);
    }

    private function execute(User $actor): array
    {
        $this->assertGuards();
        $locks = ['iuoamc_legacy_certificates_v1', 'iuoamc_legacy_certificate_trash_v1'];
        $held = [];
        try {
            foreach ($locks as $name) {
                $lock = DB::selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$name]);
                $this->check((int) ($lock->acquired ?? 0) === 1, 'LOCK_UNAVAILABLE'); $held[] = $name;
            }
            return DB::transaction(function () use ($actor): array {
                $import = LegacyCertificateImport::query()->lockForUpdate()->firstOrFail();
                // Full authenticated manifest and rows, with a verified purge-aware replay path.
                app(LegacyCertificateRegistry::class)->verifyImport((int) $import->id);
                $state = $this->reconciliation($import);
                if ($state['purged'] !== 0) { return $state + ['replay' => true]; }
                $selection = $this->selection($import, true);
                $ids = array_column($selection['exclusions'], 'local_id');
                $remaining = $this->rawRows($ids);
                $this->check(count($remaining) === self::REMAINING, 'REMAINING_COUNT');
                $payload = ['schema' => self::SCHEMA, 'snapshot_id' => LegacyCertificateRegistry::SNAPSHOT,
                    'source_database' => LegacyCertificateRegistry::SOURCE_DATABASE,
                    'source_table' => LegacyCertificateRegistry::SOURCE_TABLE, 'import_id' => (int) $import->id,
                    'original_count' => LegacyCertificateRegistry::RECORD_COUNT, 'deleted_count' => self::COUNT,
                    'remaining_count' => self::REMAINING, 'groups' => self::GROUPS,
                    'import_payload_sha256' => (string) $import->payload_sha256,
                    'import_manifest_hmac_sha256' => hash('sha256', (string) $import->manifest_hmac),
                    'exclusions_sha256' => $this->digest($selection['exclusions']),
                    'remaining_raw_rows_sha256' => $this->digest($remaining),
                    'trash_selection_sha256' => $selection['trash']['selection_sha256'],
                    'trash_audit_id' => (int) $selection['trash_audit']->id,
                    'trash_manifest_sha256' => $selection['trash_audit']->metadata['manifest_payload_sha256'],
                    'actor_id' => (int) $actor->id, 'occurred_at_utc' => now()->utc()->format('Y-m-d\TH:i:s\Z')];
                $proofHash = $this->digest($payload);
                $audit = AuditTrail::record(self::EVENT, null, [], [], [
                    'schema' => self::SCHEMA, 'scope' => 'already_trashed_shared_nonempty_barcodes',
                    'proof_sha256' => $proofHash, 'deleted_count' => self::COUNT,
                    'remaining_count' => self::REMAINING, 'exclusions_sha256' => $payload['exclusions_sha256'],
                    'trash_selection_sha256' => $payload['trash_selection_sha256'],
                    'old_source_modified' => false, 'reimport_blocked' => true,
                ], (int) $actor->id);
                $proof = LegacyCertificatePurgeProof::create(['snapshot_id' => LegacyCertificateRegistry::SNAPSHOT,
                    'import_id' => (int) $import->id, 'payload' => $payload,
                    'payload_sha256' => $proofHash, 'audit_id' => (int) $audit->id]);
                foreach ($selection['exclusions'] as $row) {
                    LegacyCertificateExclusion::create(['purge_id' => (int) $proof->id] + $row);
                }
                $deleted = DB::table('legacy_certificates')->where('import_id', $import->id)->whereIn('id', $ids)->delete();
                $this->check($deleted === self::COUNT, 'DELETE_COUNT');
                $state = $this->reconciliation($import);
                $verified = app(LegacyCertificateRegistry::class)->verifyImport((int) $import->id);
                $this->check(($verified['verified'] ?? null) === self::REMAINING, 'REMAINING_HMAC_VERIFICATION');
                return $state + ['replay' => false];
            }, 1);
        } finally {
            foreach (array_reverse($held) as $name) { DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$name]); }
        }
    }

    private function selection(LegacyCertificateImport $import, bool $lock = false): array
    {
        $registry = app(LegacyCertificateRegistry::class);
        $registry->verifyImport((int) $import->id);
        $trash = app(LegacyCertificateTrash::class)->preview();
        $active = app(LegacyCertificateTrash::class)->activeIds();
        $this->check(($trash['state'] ?? null) === 'trashed' && ($trash['affected'] ?? null) === self::COUNT
            && ($trash['groups'] ?? null) === self::GROUPS && count($active) === self::COUNT
            && ($trash['local_ids'] ?? null) === $active, 'SIGNED_TRASH_SELECTION_REQUIRED');
        $query = LegacyCertificate::query()->where('import_id', $import->id)->orderBy('id');
        if ($lock) { $query->lockForUpdate(); }
        $all = $query->get();
        $this->check($all->count() === LegacyCertificateRegistry::RECORD_COUNT, 'ORIGINAL_COUNT');
        $exclusions = []; $groupCounts = [];
        foreach ($all as $row) {
            $this->check($registry->verifyRecord($row, $import)['passed'] === true, 'ORIGINAL_ROW_INTEGRITY');
            if (! in_array((int) $row->id, $active, true)) { continue; }
            $this->check($row->snapshot_id === LegacyCertificateRegistry::SNAPSHOT
                && $row->source_database === LegacyCertificateRegistry::SOURCE_DATABASE
                && $row->source_table === LegacyCertificateRegistry::SOURCE_TABLE
                && $row->is_ambiguous === true && $row->barcode_kind === 'value'
                && $row->candidate_count > 1, 'TARGET_SCOPE');
            $groupCounts[$row->barcode_sha256] = ($groupCounts[$row->barcode_sha256] ?? 0) + 1;
            $exclusions[] = ['snapshot_id' => (string) $row->snapshot_id,
                'source_database' => (string) $row->source_database, 'source_table' => (string) $row->source_table,
                'source_id' => (string) $row->source_id, 'local_id' => (int) $row->id,
                'source_row_sha256' => (string) $row->source_row_sha256];
        }
        $this->check(count($exclusions) === self::COUNT && count($groupCounts) === self::GROUPS, 'TARGET_COUNT');
        $audit = AuditLog::query()->where('event', self::TRASH_EVENT)->orderByDesc('id')->first();
        $meta = $audit?->metadata ?? [];
        $this->check($audit !== null && ($meta['operation'] ?? null) === 'trashed'
            && ($meta['selection_sha256'] ?? null) === $trash['selection_sha256']
            && is_string($meta['manifest_payload_sha256'] ?? null)
            && app(IntegrityService::class)->verifyAuditLog($audit)['valid'] === true, 'TRASH_AUDIT_REQUIRED');
        return ['exclusions' => $exclusions, 'trash' => $trash, 'trash_audit' => $audit];
    }

    private function exclusions(int $proofId): array
    {
        return LegacyCertificateExclusion::query()->where('purge_id', $proofId)->orderBy('local_id')->get()
            ->map(fn ($row) => ['snapshot_id' => (string) $row->snapshot_id,
                'source_database' => (string) $row->source_database, 'source_table' => (string) $row->source_table,
                'source_id' => (string) $row->source_id, 'local_id' => (int) $row->local_id,
                'source_row_sha256' => (string) $row->source_row_sha256])->all();
    }

    /** Every stored column, including ciphertext, must remain byte-identical after removal. */
    private function rawRows(array $exclude = []): array
    {
        $query = DB::table('legacy_certificates')->select(self::RAW_COLUMNS)->orderBy('id');
        if ($exclude !== []) { $query->whereNotIn('id', $exclude); }
        return $query->get()->map(function ($row): array {
            $values = [];
            foreach ((array) $row as $key => $value) { $values[$key] = $value === null ? null : (string) $value; }
            ksort($values, SORT_STRING); return $values;
        })->all();
    }

    private function unpurged(): array
    {
        return ['purged' => 0, 'affected' => 0, 'remaining' => LegacyCertificateRegistry::RECORD_COUNT,
            'local_ids' => [], 'source_ids' => [], 'proof_sha256' => null];
    }

    /** Shared by migration and runtime assertions; no dynamic user-supplied SQL. */
    public static function triggerDefinitions(): array
    {
        $guard = "BEGIN IF EXISTS (SELECT 1 FROM legacy_certificate_exclusions e WHERE e.source_database = NEW.source_database AND e.source_table = NEW.source_table AND e.source_id = NEW.source_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEGACY_CERTIFICATE_PERMANENTLY_EXCLUDED'; END IF; END";
        $immutable = "BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEGACY_PURGE_EVIDENCE_IMMUTABLE'; END";
        return [
            'lcp_reimport_insert_v1' => ['legacy_certificates', 'INSERT', $guard],
            'lcp_reimport_update_v1' => ['legacy_certificates', 'UPDATE', $guard],
            'lcp_exclusion_update_v1' => ['legacy_certificate_exclusions', 'UPDATE', $immutable],
            'lcp_exclusion_delete_v1' => ['legacy_certificate_exclusions', 'DELETE', $immutable],
            'lcp_proof_update_v1' => ['legacy_certificate_purge_proofs', 'UPDATE', $immutable],
            'lcp_proof_delete_v1' => ['legacy_certificate_purge_proofs', 'DELETE', $immutable],
        ];
    }

    public function assertGuards(): void
    {
        foreach (['legacy_certificates', 'audit_logs', 'legacy_certificate_purge_proofs', 'legacy_certificate_exclusions'] as $table) {
            $row = DB::selectOne('SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
            $this->check($row !== null && strtolower((string) $row->engine) === 'innodb', 'TRANSACTIONAL_SCHEMA_REQUIRED');
        }
        $rows = DB::select('SELECT TRIGGER_NAME AS name, EVENT_OBJECT_TABLE AS table_name, EVENT_MANIPULATION AS event_name, ACTION_TIMING AS timing, ACTION_STATEMENT AS body FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()');
        $byName = []; foreach ($rows as $row) { $byName[$row->name] = $row; }
        foreach (self::triggerDefinitions() as $name => [$table, $event, $body]) {
            $row = $byName[$name] ?? null;
            $this->check($row !== null && $row->table_name === $table && $row->event_name === $event
                && $row->timing === 'BEFORE' && self::sqlNormalized($row->body) === self::sqlNormalized($body), 'EXCLUSION_GUARD_MISSING_OR_CHANGED');
        }
    }

    public static function sqlNormalized(string $sql): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim(str_replace('`', '', $sql))));
    }

    private function authorize(User $actor): void
    {
        $this->targetOnly();
        $this->check(PHP_SAPI === 'cli' && app()->runningInConsole() && DB::transactionLevel() === 0, 'CLI_WITHOUT_OUTER_TRANSACTION_REQUIRED');
        $fresh = User::query()->findOrFail($actor->getKey());
        $this->check($fresh->status === 'active' && $fresh->hasRole('super-admin'), 'ACTIVE_SUPER_ADMIN_REQUIRED');
    }

    private function targetOnly(): void
    {
        $connection = DB::connection();
        $this->check(app()->environment('production') && config('app.debug') === false
            && $connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === LegacyCertificateRegistry::TARGET_DATABASE
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)
            && $connection->getTablePrefix() === '', 'TARGET_DATABASE');
    }

    private function canonical(array $value): string
    {
        $sort = function (mixed $item) use (&$sort): mixed {
            if (! is_array($item)) { return $item; }
            if (! array_is_list($item)) { ksort($item, SORT_STRING); }
            foreach ($item as $key => $child) { $item[$key] = $sort($child); }
            return $item;
        };
        return json_encode($sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function digest(array $value): string { return hash('sha256', $this->canonical($value)); }
    private function check(bool $ok, string $label): void
    {
        if (! $ok) { throw new RuntimeException('LEGACY_PURGE_'.$label); }
    }
}
