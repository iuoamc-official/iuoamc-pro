<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\LegacyCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** A recoverable, signed visibility overlay. Imported originals are never mutated. */
final class LegacyCertificateTrash
{
    private const SCHEMA = 'iuoamc-legacy-certificate-trash-v1';
    private const SCOPE = 'all_shared_nonempty_barcodes';
    private const EVENT = 'legacy.certificates.visibility.changed';
    private const LOCK = 'iuoamc_legacy_certificate_trash_v1';
    private const COUNT = 353;
    private const GROUPS = 11;

    /** Read-only. Installer retries explicitly call recover() before this method. */
    public function preview(): array
    {
        $this->targetOnly();
        $this->require(! $this->permanentlyPurged(), 'PERMANENT_DELETION_CANNOT_BE_RESTORED');
        $this->require(! $this->pendingExists(), 'PENDING_RECOVERY_REQUIRED');
        $selection = $this->selection();
        $manifest = $this->readManifest($selection);
        return ['affected' => self::COUNT, 'groups' => self::GROUPS, 'scope' => self::SCOPE,
            'local_ids' => array_column($selection['records'], 'local_id'),
            'source_ids' => array_column($selection['records'], 'source_id'),
            'selection_sha256' => $selection['selection_sha256'], 'barcode_groups' => $selection['barcode_groups'],
            'state' => $manifest === null ? 'visible' : $this->last($manifest)['payload']['state'],
            'original_rows_preserved' => true];
    }

    /** IDs excluded from ordinary registry views. A partial commit never silently revives them. */
    public function activeIds(): array
    {
        $this->targetOnly();
        if ($this->permanentlyPurged()) { return []; }
        $this->require(! $this->pendingExists(), 'PENDING_RECOVERY_REQUIRED');
        if (! $this->exists($this->path())) {
            $this->require($this->latestAudit() === null, 'MISSING_MANIFEST');
            return [];
        }
        $manifest = $this->readManifest($this->selection());
        $last = $this->last($manifest)['payload'];
        return $last['state'] === 'trashed' ? array_column($last['records'], 'local_id') : [];
    }

    /** Finish only a previously prepared exact operation, without creating a second audit event. */
    public function recover(User $actor): array
    {
        $this->authorize($actor);
        $this->require(! $this->permanentlyPurged(), 'PERMANENT_DELETION_CANNOT_BE_RESTORED');
        return $this->locked(function () use ($actor): array {
            if (! $this->pendingExists()) { return ['recovered' => false]; }
            return $this->recoverLocked($actor, $this->selection());
        });
    }

    /** Trash or restore the explicitly authorized complete 353-record, 11-group scope. */
    public function apply(User $actor, bool $restore = false): array
    {
        $this->authorize($actor);
        $this->require(! $this->permanentlyPurged(), 'PERMANENT_DELETION_CANNOT_BE_RESTORED');
        return $this->locked(function () use ($actor, $restore): array {
            $selection = $this->selection();
            if ($this->pendingExists()) { $this->recoverLocked($actor, $selection); }
            $manifest = $this->readManifest($selection);
            $desired = $restore ? 'restored' : 'trashed';
            if ($manifest !== null && $this->last($manifest)['payload']['state'] === $desired) {
                return $this->result($this->last($manifest), true);
            }
            $this->require($manifest !== null || ! $restore, 'NOT_PREVIOUSLY_TRASHED');
            $this->require(count($manifest['entries'] ?? []) < 100, 'HISTORY_LIMIT');
            $previous = $manifest === null ? null : $this->last($manifest)['payload_sha256'];
            $payload = ['schema' => self::SCHEMA, 'scope' => self::SCOPE,
                'snapshot_id' => LegacyCertificateRegistry::SNAPSHOT,
                'source_database' => LegacyCertificateRegistry::SOURCE_DATABASE,
                'source_table' => LegacyCertificateRegistry::SOURCE_TABLE,
                'selection_sha256' => $selection['selection_sha256'], 'barcode_groups' => $selection['barcode_groups'],
                'records' => $selection['records'], 'affected' => self::COUNT, 'groups' => self::GROUPS,
                'state' => $desired, 'actor_id' => (int) $actor->id, 'previous_payload_sha256' => $previous,
                'operation_id' => bin2hex(random_bytes(24)), 'occurred_at_utc' => now()->utc()->format('Y-m-d\TH:i:s\Z')];
            $journal = ['schema' => self::SCHEMA.'-pending', 'base_manifest' => $manifest,
                'payload' => $payload, 'payload_sha256' => $this->digest($payload)];
            $journal['journal_hmac'] = $this->journalMac($journal);
            // Durable intent exists BEFORE any audit commit, including the full byte-exact payload and base history.
            $this->require(! $this->pendingExists(), 'PENDING_CONFLICT');
            $this->writePrivate($this->pendingPath(), $journal);
            return $this->recoverLocked($actor, $selection);
        });
    }

    private function recoverLocked(User $actor, array $selection): array
    {
        $journal = $this->readPrivate($this->pendingPath());
        $mac = $journal['journal_hmac'] ?? null;
        unset($journal['journal_hmac']);
        $this->require(is_string($mac) && hash_equals($this->journalMac($journal), $mac)
            && ($journal['schema'] ?? null) === self::SCHEMA.'-pending'
            && is_array($journal['payload'] ?? null) && is_string($journal['payload_sha256'] ?? null)
            && array_key_exists('base_manifest', $journal), 'PENDING_INTEGRITY');
        $base = $journal['base_manifest'];
        $this->require($base === null || is_array($base), 'PENDING_BASE');
        if ($base !== null) { $this->validateManifest($base, $selection, false); }
        $previous = $base === null ? null : $this->last($base)['payload_sha256'];
        $previousState = $base === null ? null : $this->last($base)['payload']['state'];
        $previousAuditId = $base === null ? null : $this->last($base)['audit_id'];
        $payload = $journal['payload'];
        $this->validatePayload($payload, $selection, $previous, $previousState);
        $this->require(hash_equals($this->digest($payload), $journal['payload_sha256']), 'PENDING_PAYLOAD_HASH');
        $matches = AuditLog::query()->where('event', self::EVENT)->orderBy('id')->get()
            ->filter(fn ($log) => ($log->metadata['operation_id'] ?? null) === $payload['operation_id'])->values();
        $this->require($matches->count() <= 1, 'DUPLICATE_OPERATION_AUDIT');
        $audit = $matches->first();
        $latest = $this->latestAudit();
        $this->require(($latest?->id === null ? null : (int) $latest->id)
            === ($audit === null ? $previousAuditId : (int) $audit->id), 'PENDING_LATEST_AUDIT');
        // Refuse to overwrite a different visibility manifest. An absent file is recoverable from the signed base.
        $current = $this->exists($this->path()) ? $this->readPrivate($this->path()) : null;
        if ($current !== null) {
            $currentDigest = $this->digest($current);
            $isBase = $base !== null && hash_equals($this->digest($base), $currentDigest);
            if (! $isBase) {
                $this->validateManifest($current, $selection, true);
                $currentLast = $this->last($current);
                $this->require($currentLast['payload_sha256'] === $journal['payload_sha256']
                    && $audit !== null && $currentLast['audit_id'] === (int) $audit->id, 'PENDING_CURRENT_CONFLICT');
            }
        }
        if ($audit === null) {
            // Recovery may be initiated by another active administrator; attribution remains to the signed intent's actor.
            $requester = User::query()->findOrFail($payload['actor_id']);
            $this->require($requester->status === 'active' && $requester->hasRole('super-admin'), 'PENDING_ACTOR_INACTIVE');
            $audit = AuditTrail::record(self::EVENT, null, [], [], [
                'schema' => self::SCHEMA, 'scope' => self::SCOPE, 'snapshot_id' => LegacyCertificateRegistry::SNAPSHOT,
                'manifest_payload_sha256' => $journal['payload_sha256'], 'selection_sha256' => $selection['selection_sha256'],
                'affected' => self::COUNT, 'groups' => self::GROUPS, 'operation' => $payload['state'],
                'operation_id' => $payload['operation_id'], 'requested_by' => $payload['actor_id'],
                'completed_by' => (int) $actor->id, 'previous_payload_sha256' => $previous,
                'original_rows_preserved' => true,
            ], $payload['actor_id']);
        }
        $entry = ['payload' => $payload, 'payload_sha256' => $journal['payload_sha256'], 'audit_id' => (int) $audit->id];
        $this->validateAudit($entry, $previousAuditId ?? 0);
        $complete = $base ?? ['schema' => self::SCHEMA, 'entries' => []];
        $complete['entries'][] = $entry;
        if ($current === null || ! hash_equals($this->digest($complete), $this->digest($current))) {
            $this->writePrivate($this->path(), $complete);
        }
        $this->readManifest($selection);
        $this->require(unlink($this->pendingPath()), 'PENDING_CLEAR');
        $this->syncDirectory(dirname($this->path()));
        return $this->result($entry, false) + ['recovered' => true];
    }

    private function permanentlyPurged(): bool
    {
        $import = app(LegacyCertificateRegistry::class)->currentImport();
        return app(LegacyCertificatePurge::class)->reconciliation($import)['purged'] > 0;
    }

    private function selection(): array
    {
        $registry = app(LegacyCertificateRegistry::class);
        $import = $registry->currentImport();
        $records = LegacyCertificate::query()->where('import_id', $import->id)->orderBy('id')->get();
        $this->require($records->count() === LegacyCertificateRegistry::RECORD_COUNT, 'ORIGINAL_COUNT');
        $groups = [];
        foreach ($records as $record) {
            $this->require($record->snapshot_id === LegacyCertificateRegistry::SNAPSHOT
                && $record->source_database === LegacyCertificateRegistry::SOURCE_DATABASE
                && $record->source_table === LegacyCertificateRegistry::SOURCE_TABLE
                && $registry->verifyRecord($record, $import)['passed'], 'ORIGINAL_ROW_INTEGRITY');
            $barcode = $this->originalBytes($record, 'barcode');
            if ($barcode === null || $barcode === '') {
                $this->require(! $record->is_ambiguous, 'EMPTY_BARCODE_AMBIGUITY');
                continue;
            }
            $hash = hash('sha256', $barcode);
            $this->require($record->barcode_kind === 'value' && $record->barcode_sha256 === $hash, 'BARCODE_BYTES');
            $groups[$hash][] = $record;
        }
        $selected = [];
        $groupRows = [];
        ksort($groups, SORT_STRING);
        foreach ($groups as $hash => $members) {
            $ids = array_map(fn ($record) => (string) $record->source_id, $members);
            sort($ids, SORT_STRING);
            $shared = count($members) > 1;
            foreach ($members as $record) {
                $candidates = $record->sql_candidate_source_ids;
                $this->require(is_array($candidates) && array_is_list($candidates), 'CANDIDATE_FORMAT');
                sort($candidates, SORT_STRING);
                $this->require($record->is_ambiguous === $shared && $record->candidate_count === count($members)
                    && $candidates === $ids, 'CANDIDATE_GROUP_MISMATCH');
                if ($shared) {
                    $selected[] = ['local_id' => (int) $record->id, 'source_id' => (string) $record->source_id,
                        'source_row_sha256' => (string) $record->source_row_sha256, 'barcode_sha256' => $hash];
                }
            }
            if ($shared) { $groupRows[] = ['barcode_sha256' => $hash, 'count' => count($members)]; }
        }
        usort($selected, fn ($a, $b) => $a['local_id'] <=> $b['local_id']);
        $this->require(count($selected) === self::COUNT && count($groupRows) === self::GROUPS, 'TARGET_COUNT');
        $values = ['records' => $selected, 'barcode_groups' => $groupRows];
        return $values + ['selection_sha256' => $this->digest($values)];
    }

    private function originalBytes(LegacyCertificate $record, string $name): ?string
    {
        $value = $record->original_fields[$name] ?? null;
        $this->require(is_array($value) && isset($value['kind'], $value['base64']), 'ORIGINAL_FIELD');
        if ($value['kind'] === 'N') { return null; }
        $this->require(in_array($value['kind'], ['B', 'D'], true), 'ORIGINAL_KIND');
        $decoded = base64_decode($value['base64'], true);
        $this->require(is_string($decoded), 'ORIGINAL_ENCODING');
        return $decoded;
    }

    private function readManifest(array $selection): ?array
    {
        if (! $this->exists($this->path())) {
            $this->require($this->latestAudit() === null, 'MISSING_MANIFEST');
            return null;
        }
        $manifest = $this->readPrivate($this->path());
        $this->validateManifest($manifest, $selection, true);
        return $manifest;
    }

    private function validateManifest(array $manifest, array $selection, bool $latest): void
    {
        $this->require(($manifest['schema'] ?? null) === self::SCHEMA
            && is_array($manifest['entries'] ?? null) && array_is_list($manifest['entries'])
            && count($manifest['entries']) >= 1 && count($manifest['entries']) <= 100, 'MANIFEST_SCHEMA');
        $previous = null; $state = null; $auditId = 0;
        foreach ($manifest['entries'] as $entry) {
            $this->require(is_array($entry) && is_array($entry['payload'] ?? null)
                && is_string($entry['payload_sha256'] ?? null) && is_int($entry['audit_id'] ?? null), 'ENTRY_SCHEMA');
            $this->require(hash_equals($this->digest($entry['payload']), $entry['payload_sha256']), 'PAYLOAD_HASH');
            $this->validatePayload($entry['payload'], $selection, $previous, $state);
            $this->validateAudit($entry, $auditId);
            $previous = $entry['payload_sha256']; $state = $entry['payload']['state']; $auditId = $entry['audit_id'];
        }
        if ($latest) { $this->require((int) ($this->latestAudit()?->id ?? 0) === $auditId, 'LATEST_AUDIT_MISMATCH'); }
    }

    private function validatePayload(array $p, array $selection, ?string $previous, ?string $previousState): void
    {
        $this->require(($p['schema'] ?? null) === self::SCHEMA && ($p['scope'] ?? null) === self::SCOPE
            && ($p['snapshot_id'] ?? null) === LegacyCertificateRegistry::SNAPSHOT
            && ($p['source_database'] ?? null) === LegacyCertificateRegistry::SOURCE_DATABASE
            && ($p['source_table'] ?? null) === LegacyCertificateRegistry::SOURCE_TABLE
            && ($p['selection_sha256'] ?? null) === $selection['selection_sha256']
            && is_array($p['records'] ?? null) && $this->canonical($p['records']) === $this->canonical($selection['records'])
            && is_array($p['barcode_groups'] ?? null) && $this->canonical($p['barcode_groups']) === $this->canonical($selection['barcode_groups'])
            && ($p['affected'] ?? null) === self::COUNT && ($p['groups'] ?? null) === self::GROUPS
            && ($p['previous_payload_sha256'] ?? null) === $previous
            && is_int($p['actor_id'] ?? null) && $p['actor_id'] > 0
            && is_string($p['operation_id'] ?? null) && preg_match('/^[a-f0-9]{48}$/D', $p['operation_id']) === 1
            && is_string($p['occurred_at_utc'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $p['occurred_at_utc']) === 1
            && in_array($p['state'] ?? null, ['trashed', 'restored'], true) && $p['state'] !== $previousState
            && ($previousState !== null || $p['state'] === 'trashed'), 'PAYLOAD_SCOPE');
    }

    private function validateAudit(array $entry, int $previousId): void
    {
        $audit = AuditLog::query()->find($entry['audit_id']);
        $p = $entry['payload']; $m = $audit?->metadata ?? [];
        $this->require($audit !== null && $audit->event === self::EVENT && (int) $audit->actor_id === $p['actor_id']
            && $entry['audit_id'] > $previousId && ($m['schema'] ?? null) === self::SCHEMA
            && ($m['scope'] ?? null) === self::SCOPE && ($m['snapshot_id'] ?? null) === LegacyCertificateRegistry::SNAPSHOT
            && ($m['manifest_payload_sha256'] ?? null) === $entry['payload_sha256']
            && ($m['selection_sha256'] ?? null) === $p['selection_sha256']
            && ($m['affected'] ?? null) === self::COUNT && ($m['groups'] ?? null) === self::GROUPS
            && ($m['operation'] ?? null) === $p['state'] && ($m['operation_id'] ?? null) === $p['operation_id']
            && ($m['requested_by'] ?? null) === $p['actor_id']
            && ($m['previous_payload_sha256'] ?? null) === $p['previous_payload_sha256']
            && ($m['original_rows_preserved'] ?? null) === true
            && app(IntegrityService::class)->verifyAuditLog($audit)['valid'] === true, 'AUDIT_BINDING');
    }

    private function readPrivate(string $path): array
    {
        $this->privateFile($path);
        $bytes = file_get_contents($path);
        $this->require(is_string($bytes), 'MANIFEST_READ');
        $value = json_decode($bytes, true, 128, JSON_THROW_ON_ERROR);
        $this->require(is_array($value), 'MANIFEST_JSON');
        return $value;
    }

    private function writePrivate(string $path, array $value): void
    {
        if ($this->exists($path)) { $this->privateFile($path); }
        $temporary = dirname($path).'/.legacy-certificate-trash.'.bin2hex(random_bytes(12)).'.tmp';
        $handle = fopen($temporary, 'x+b');
        $this->require(is_resource($handle), 'TEMP_CREATE');
        try {
            $this->require(chmod($temporary, 0600), 'TEMP_PERMISSIONS');
            $bytes = $this->canonical($value)."\n"; $written = 0;
            while ($written < strlen($bytes)) {
                $n = fwrite($handle, substr($bytes, $written));
                $this->require(is_int($n) && $n > 0, 'TEMP_WRITE'); $written += $n;
            }
            $this->require(fflush($handle) && fsync($handle), 'TEMP_SYNC');
            fclose($handle); $handle = null;
            $this->require(rename($temporary, $path), 'MANIFEST_SWITCH');
            $this->syncDirectory(dirname($path));
            $this->privateFile($path);
        } finally {
            if (is_resource($handle)) { fclose($handle); }
            if (is_file($temporary)) { unlink($temporary); }
        }
    }

    private function syncDirectory(string $directory): void
    {
        $handle = fopen($directory, 'r');
        $this->require(is_resource($handle), 'DIRECTORY_OPEN');
        try { $this->require(fsync($handle), 'DIRECTORY_SYNC'); }
        finally { fclose($handle); }
    }

    private function path(): string
    {
        // Check canonical application paths, never ancestors outside the FPM open_basedir.
        // The installer independently pins the application root owner to iuoamcnext.
        $appRoot = base_path();
        clearstatcache(true, $appRoot);
        $rootStat = lstat($appRoot);
        $this->require(is_array($rootStat) && realpath($appRoot) === $appRoot
            && ! is_link($appRoot) && ($rootStat['mode'] & 0170000) === 0040000
            && is_int($rootStat['uid']) && $rootStat['uid'] > 0
            && ($rootStat['mode'] & 0002) === 0, 'APP_ROOT_OWNER');
        $expectedAppUid = $rootStat['uid'];
        if (function_exists('posix_geteuid')) {
            $this->require(posix_geteuid() === $expectedAppUid, 'APP_PROCESS_OWNER');
        }
        $directory = storage_path('app');
        $this->require($directory === $appRoot.'/storage/app'
            && realpath($directory) === $directory, 'STORAGE_PATH');
        clearstatcache(true, $directory);
        $directoryStat = lstat($directory);
        $this->require(is_array($directoryStat) && ! is_link($directory)
            && ($directoryStat['mode'] & 0170000) === 0040000
            && $directoryStat['uid'] === $expectedAppUid
            && ($directoryStat['mode'] & 0002) === 0, 'APP_STORAGE_OWNER');
        return $directory.'/legacy-certificate-trash.json';
    }

    private function pendingPath(): string { return $this->path().'.pending.json'; }
    private function pendingExists(): bool { return $this->exists($this->pendingPath()); }
    private function exists(string $path): bool { clearstatcache(true, $path); return file_exists($path) || is_link($path); }
    private function latestAudit(): ?AuditLog { return AuditLog::query()->where('event', self::EVENT)->orderByDesc('id')->first(); }
    private function last(array $manifest): array { return $manifest['entries'][array_key_last($manifest['entries'])]; }

    private function privateFile(string $path): void
    {
        // Reuse the app-root and optional effective-UID checks without requiring POSIX in FPM.
        $this->path();
        $expectedAppUid = fileowner(base_path());
        clearstatcache(true, $path); $stat = lstat($path);
        $this->require(is_array($stat) && ! is_link($path) && is_file($path)
            && is_int($expectedAppUid) && $expectedAppUid > 0
            && ($stat['mode'] & 0777) === 0600 && $stat['uid'] === $expectedAppUid
            && $stat['nlink'] === 1 && $stat['size'] > 0 && $stat['size'] <= 32 * 1024 * 1024, 'PRIVATE_MANIFEST_FILE');
    }

    private function result(array $entry, bool $replay): array
    {
        return ['state' => $entry['payload']['state'], 'affected' => self::COUNT, 'groups' => self::GROUPS, 'scope' => self::SCOPE,
            'local_ids' => array_column($entry['payload']['records'], 'local_id'),
            'source_ids' => array_column($entry['payload']['records'], 'source_id'), 'replay' => $replay,
            'audit_id' => $entry['audit_id'], 'manifest_sha256' => $entry['payload_sha256'],
            'selection_sha256' => $entry['payload']['selection_sha256'], 'original_rows_preserved' => true];
    }

    private function authorize(User $actor): void
    {
        $this->targetOnly();
        $this->require(PHP_SAPI === 'cli' && app()->runningInConsole(), 'CLI_ONLY');
        $fresh = User::query()->findOrFail($actor->getKey());
        $this->require($fresh->status === 'active' && $fresh->hasRole('super-admin'), 'ACTIVE_SUPER_ADMIN_REQUIRED');
        $this->require(DB::transactionLevel() === 0, 'NO_OUTER_TRANSACTION');
    }

    private function locked(callable $work): array
    {
        $lock = DB::selectOne("SELECT GET_LOCK('".self::LOCK."', 10) AS acquired");
        $this->require((int) ($lock->acquired ?? 0) === 1, 'LOCK_UNAVAILABLE');
        try { return $work(); }
        finally { DB::selectOne("SELECT RELEASE_LOCK('".self::LOCK."') AS released"); }
    }

    private function targetOnly(): void
    {
        $connection = DB::connection();
        $this->require(app()->environment('production') && config('app.debug') === false
            && $connection->getDriverName() === 'mysql'
            && $connection->getDatabaseName() === LegacyCertificateRegistry::TARGET_DATABASE
            && in_array($connection->getConfig('host'), ['localhost', '127.0.0.1', '::1'], true)
            && $connection->getTablePrefix() === '', 'TARGET_DATABASE');
    }

    private function journalMac(array $value): string
    {
        $key = config('app.key');
        $this->require(is_string($key) && $key !== '', 'KEY_UNAVAILABLE');
        if (str_starts_with($key, 'base64:')) { $key = base64_decode(substr($key, 7), true); }
        $this->require(is_string($key) && strlen($key) >= 32, 'KEY_INVALID');
        $derived = hash_hmac('sha256', 'IUOAMC-LEGACY-CERTIFICATE-TRASH-PENDING-V1', $key, true);
        return hash_hmac('sha256', $this->canonical($value), $derived);
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
    private function require(bool $condition, string $label): void
    {
        if (! $condition) { throw new RuntimeException('LEGACY_TRASH_'.$label); }
    }
}
