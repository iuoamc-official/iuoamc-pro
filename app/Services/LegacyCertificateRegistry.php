<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\LegacyCertificate;
use App\Models\LegacyCertificateImport;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Provenance register with signed, explicit permanent exclusions; no credential-validity decisions. */
final class LegacyCertificateRegistry
{
    public const SNAPSHOT = '20260908T092159Z-da04f5bb';
    public const SOURCE_DATABASE = 'iuoamcuk_web';
    public const SOURCE_TABLE = 'certificates';
    public const TARGET_DATABASE = 'iuoamcnext_nextcore';
    public const RECORD_COUNT = 1725;
    public const CAPTURED_AT = '2026-09-08T09:27:07.147889+00:00';
    private const FIELD_NAMES = ['id', 'barcode', 'user', 'name', 'dob', 'email', 'phone', 'title',
        'mark', 'start', 'end', 'country', 'price', 'design', 'type', 'reg_number', 'ownership',
        'created_at', 'updated_at', 'status'];
    private const DISPLAY_MAP = ['holder_name' => 'name', 'title' => 'title', 'registration_number' => 'reg_number',
        'barcode' => 'barcode', 'country' => 'country', 'valid_from' => 'start', 'valid_until' => 'end',
        'stored_status' => 'status', 'type' => 'type', 'design' => 'design'];
    private const STORAGE_MAP = ['holder_name' => 'holder_name', 'certificate_title' => 'title',
        'registration_number' => 'registration_number', 'barcode' => 'barcode', 'source_country' => 'country',
        'valid_from' => 'valid_from', 'valid_until' => 'valid_until', 'stored_status' => 'stored_status',
        'legacy_type' => 'type', 'legacy_design' => 'design'];

    /** @return array{import_id:int,records:int,imported:bool,replay:bool,verified:int,audit_id:int,snapshot_id:string} */
    public function importFile(string $path, string $expectedSha256): array
    {
        $this->consoleOnly();
        $this->targetOnly();
        $payload = $this->readPinnedPayload($path, $expectedSha256);
        $validated = $this->validatePayload($payload);
        // Connection-scoped lock serializes retries across processes without a mutable lock table.
        $lock = DB::selectOne("SELECT GET_LOCK('iuoamc_legacy_certificates_v1', 10) AS acquired");
        if ((int) ($lock->acquired ?? 0) !== 1) { throw new RuntimeException('LEGACY_IMPORT_LOCK_UNAVAILABLE'); }
        try {
            return DB::transaction(function () use ($payload, $validated, $expectedSha256): array {
                $existing = LegacyCertificateImport::query()->where('snapshot_id', self::SNAPSHOT)->lockForUpdate()->first();
                if ($existing) {
                    if (! hash_equals((string) $existing->payload_sha256, $expectedSha256)) {
                        throw new RuntimeException('LEGACY_IMPORT_REPLAY_PAYLOAD_CONFLICT');
                    }
                    $verified = $this->verifyImport((int) $existing->id);
                    return ['import_id' => (int) $existing->id, 'records' => $verified['verified'],
                        'imported' => false, 'replay' => true, 'verified' => $verified['verified'],
                        'audit_id' => (int) $existing->audit_id, 'snapshot_id' => self::SNAPSHOT];
                }
                app(LegacyCertificatePurge::class)->assertNoPriorPurge();
                if (LegacyCertificateImport::query()->exists() || LegacyCertificate::query()->exists()) {
                    throw new RuntimeException('LEGACY_IMPORT_EXISTING_RECORD_CONFLICT');
                }
                $importedAt = now()->utc()->format('Y-m-d\\TH:i:s\\Z');
                $audit = AuditTrail::record('legacy.certificates.imported', null, [], [], [
                    'schema' => 'iuoamc-legacy-certificate-import-v1', 'snapshot_id' => self::SNAPSHOT,
                    'payload_sha256' => $expectedSha256, 'source_database' => self::SOURCE_DATABASE,
                    'source_table' => self::SOURCE_TABLE, 'record_count' => self::RECORD_COUNT,
                    'ambiguous_groups' => $validated['ambiguous_groups'], 'credential_validity' => 'not_assigned',
                    'original_ids_preserved' => true, 'original_barcodes_preserved' => true,
                    'imported_at' => $importedAt,
                ]);
                $manifest = [
                    'snapshot_id' => self::SNAPSHOT, 'payload_sha256' => $expectedSha256,
                    'captured_at' => $payload['captured_at_utc'], 'imported_at' => $importedAt,
                    'source_database' => self::SOURCE_DATABASE, 'source_table' => self::SOURCE_TABLE,
                    'record_count' => self::RECORD_COUNT, 'summary' => $validated,
                    'provenance' => $payload['provenance'], 'audit_id' => (int) $audit->id,
                ];
                $import = LegacyCertificateImport::create($manifest + ['manifest_hmac' => $this->mac('manifest', $manifest)]);
                foreach ($payload['records'] as $source) {
                    $values = $this->storageValues($source, (int) $import->id);
                    $values['projection_hash'] = $this->digest($values);
                    $values['projection_hmac'] = $this->mac('certificate', $values);
                    LegacyCertificate::create($values);
                }
                $verified = $this->verifyImport((int) $import->id);
                return ['import_id' => (int) $import->id, 'records' => self::RECORD_COUNT,
                    'imported' => true, 'replay' => false, 'verified' => $verified['verified'],
                    'audit_id' => (int) $audit->id, 'snapshot_id' => self::SNAPSHOT];
            }, 1);
        } finally {
            DB::selectOne("SELECT RELEASE_LOCK('iuoamc_legacy_certificates_v1') AS released");
        }
    }

    /** Entire import integrity including its existing Ed25519 audit seal. No writes. */
    public function verifyImport(?int $importId = null): array
    {
        $this->targetOnly();
        $import = $importId === null ? LegacyCertificateImport::query()->firstOrFail()
            : LegacyCertificateImport::query()->findOrFail($importId);
        $this->requireManifest($import);
        $reconciliation = app(LegacyCertificatePurge::class)->reconciliation($import);
        $verified = 0;
        LegacyCertificate::query()->where('import_id', $import->id)->orderBy('id')->chunkById(100,
            function ($records) use (&$verified, $import): void {
                foreach ($records as $record) {
                    if (! $this->verifyRecord($record, $import)['passed']) {
                        throw new RuntimeException('LEGACY_REGISTRY_INTEGRITY_FAILED');
                    }
                    $verified++;
                }
            });
        if ($verified !== $reconciliation['remaining'] || LegacyCertificate::query()->count() !== $reconciliation['remaining']) {
            throw new RuntimeException('LEGACY_REGISTRY_RECORD_COUNT_MISMATCH');
        }
        return ['passed' => true, 'verified' => $verified, 'remaining' => $reconciliation['remaining'],
            'purged' => $reconciliation['purged'], 'original_records' => self::RECORD_COUNT, 'snapshot_id' => self::SNAPSHOT,
            'audit_id' => (int) $import->audit_id, 'payload_sha256' => $import->payload_sha256];
    }

    public function currentImport(): LegacyCertificateImport
    {
        $this->targetOnly();
        $import = LegacyCertificateImport::query()->firstOrFail();
        $this->requireManifest($import);
        $reconciliation = app(LegacyCertificatePurge::class)->reconciliation($import);
        if (LegacyCertificate::query()->count() !== $reconciliation['remaining']) {
            throw new RuntimeException('LEGACY_REGISTRY_RECORD_COUNT_MISMATCH');
        }
        return $import;
    }

    /** HMAC authenticates copied source bytes + every displayed projection and provenance field. */
    public function verifyRecord(LegacyCertificate $record, ?LegacyCertificateImport $import = null): array
    {
        try {
            $import ??= $this->currentImport();
            if ((int) $record->import_id !== (int) $import->id || $record->snapshot_id !== self::SNAPSHOT) {
                throw new RuntimeException('LEGACY_RECORD_IMPORT_MISMATCH');
            }
            $values = $this->storedProjection($record);
            $valid = hash_equals((string) $record->projection_hash, $this->digest($values));
            $values['projection_hash'] = (string) $record->projection_hash;
            $valid = $valid && hash_equals((string) $record->projection_hmac, $this->mac('certificate', $values));
            return ['passed' => $valid, 'source_hash' => (string) $record->source_row_sha256,
                'projection_hash' => (string) $record->projection_hash];
        } catch (Throwable) {
            return ['passed' => false, 'source_hash' => null, 'projection_hash' => null];
        }
    }

    private function requireManifest(LegacyCertificateImport $import): void
    {
        $fields = ['snapshot_id', 'payload_sha256', 'captured_at', 'imported_at', 'source_database',
            'source_table', 'record_count', 'summary', 'provenance', 'audit_id'];
        $manifest = [];
        foreach ($fields as $field) { $manifest[$field] = $import->{$field}; }
        if ($import->snapshot_id !== self::SNAPSHOT || $import->source_database !== self::SOURCE_DATABASE
            || $import->source_table !== self::SOURCE_TABLE || $import->record_count !== self::RECORD_COUNT
            || ! hash_equals((string) $import->manifest_hmac, $this->mac('manifest', $manifest))) {
            throw new RuntimeException('LEGACY_IMPORT_MANIFEST_INTEGRITY_FAILED');
        }
        $audit = $import->audit;
        $meta = $audit?->metadata ?? [];
        if (! $audit || $audit->event !== 'legacy.certificates.imported'
            || ($meta['payload_sha256'] ?? null) !== $import->payload_sha256
            || ($meta['snapshot_id'] ?? null) !== self::SNAPSHOT
            || ($meta['record_count'] ?? null) !== self::RECORD_COUNT
            || ($meta['source_database'] ?? null) !== self::SOURCE_DATABASE
            || ($meta['source_table'] ?? null) !== self::SOURCE_TABLE
            || ! app(IntegrityService::class)->verifyAuditLog($audit)['valid']) {
            throw new RuntimeException('LEGACY_IMPORT_AUDIT_INTEGRITY_FAILED');
        }
    }

    /** Public, side-effect-free validation also used by the packaged contract tests. */
    public function validatePayload(array $payload): array
    {
        if (($payload['schema'] ?? null) !== 'iuoamc-legacy-certificate-import-v1'
            || ($payload['snapshot_id'] ?? null) !== self::SNAPSHOT
            || ($payload['source_database'] ?? null) !== self::SOURCE_DATABASE
            || ($payload['source_table'] ?? null) !== self::SOURCE_TABLE
            || ($payload['record_count'] ?? null) !== self::RECORD_COUNT
            || ! is_array($payload['records'] ?? null) || ! array_is_list($payload['records'])
            || count($payload['records']) !== self::RECORD_COUNT
            || ! is_array($payload['provenance'] ?? null) || ! is_array($payload['summary'] ?? null)
            || ($payload['captured_at_utc'] ?? null) !== self::CAPTURED_AT) {
            throw new RuntimeException('LEGACY_IMPORT_CONTRACT_MISMATCH');
        }
        $ids = []; $barcodes = []; $candidateLists = []; $active = 0; $statuses = [];
        foreach ($payload['records'] as $row) {
            $source = $this->validateSourceRecord($row);
            $id = $source['id'];
            if (isset($ids[$id])) { throw new RuntimeException('LEGACY_IMPORT_DUPLICATE_SOURCE_ID'); }
            $ids[$id] = true;
            $barcodes[$id] = $source['barcode'];
            $candidateLists[$id] = $source['candidates'];
            $status = $row['display']['stored_status'];
            if ($status === 'active') { $active++; }
            $statusKey = $status === null ? '(null)' : $status;
            $statuses[$statusKey] = ($statuses[$statusKey] ?? 0) + 1;
        }
        $byteGroups = []; $nonemptyOccurrences = 0; $nonemptyGroups = [];
        foreach ($barcodes as $id => $barcode) {
            if ($barcode === null) { continue; }
            $groupKey = base64_encode($barcode);
            $byteGroups[$groupKey][] = (string) $id;
            if ($barcode !== '') { $nonemptyOccurrences++; $nonemptyGroups[$groupKey] = true; }
        }
        foreach ($byteGroups as &$byteGroup) {
            usort($byteGroup, fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        }
        unset($byteGroup);
        $ambiguous = 0; $groups = [];
        foreach ($candidateLists as $id => $candidates) {
            $expectedCandidates = $barcodes[$id] === null ? [] : $byteGroups[base64_encode($barcodes[$id])];
            if ($candidates !== $expectedCandidates) { throw new RuntimeException('LEGACY_IMPORT_CANDIDATE_SET_INCOMPLETE'); }
            foreach ($candidates as $candidate) {
                if (! isset($ids[$candidate]) || $barcodes[$id] !== $barcodes[$candidate]
                    || $candidateLists[$candidate] !== $candidates) {
                    throw new RuntimeException('LEGACY_IMPORT_CANDIDATE_GRAPH_MISMATCH');
                }
            }
            if ($barcodes[$id] !== null && $barcodes[$id] !== '' && count($candidates) > 1) {
                $ambiguous++;
                $groups[hash('sha256', $this->canonical($candidates))] = true;
            }
        }
        if (count($groups) !== 11 || $nonemptyOccurrences !== 1700 || count($nonemptyGroups) !== 1358) {
            throw new RuntimeException('LEGACY_IMPORT_AMBIGUITY_COUNT_MISMATCH');
        }
        ksort($statuses, SORT_STRING);
        return ['total' => self::RECORD_COUNT, 'active' => $active, 'nonactive' => self::RECORD_COUNT - $active,
            'ambiguous' => $ambiguous, 'ambiguous_groups' => count($groups), 'stored_status_counts' => $statuses,
            'credential_validity' => 'not_assigned'];
    }

    public function validateSourceRecord(mixed $row): array
    {
        if (! is_array($row) || ($row['source_database'] ?? null) !== self::SOURCE_DATABASE
            || ($row['source_table'] ?? null) !== self::SOURCE_TABLE
            || ($row['credential_validity'] ?? null) !== 'not_assigned'
            || ! is_string($row['source_id'] ?? null) || ! $this->isSourceId($row['source_id'])
            || ! is_string($row['source_row_sha256'] ?? null)
            || ! preg_match('/^[a-f0-9]{64}$/D', $row['source_row_sha256'])
            || ! is_array($row['original_fields'] ?? null) || ! is_array($row['display'] ?? null)) {
            throw new RuntimeException('LEGACY_SOURCE_RECORD_CONTRACT_MISMATCH');
        }
        $fieldNames = array_keys($row['original_fields']); $expectedNames = self::FIELD_NAMES;
        sort($fieldNames, SORT_STRING); sort($expectedNames, SORT_STRING);
        $displayNames = array_keys($row['display']); $expectedDisplay = array_keys(self::DISPLAY_MAP);
        sort($displayNames, SORT_STRING); sort($expectedDisplay, SORT_STRING);
        if ($fieldNames !== $expectedNames || $displayNames !== $expectedDisplay) {
            throw new RuntimeException('LEGACY_SOURCE_COLUMN_SET_MISMATCH');
        }
        $decoded = [];
        foreach ($row['original_fields'] as $name => $value) { $decoded[$name] = $this->decodeField($value); }
        if (! hash_equals($row['source_row_sha256'], $this->sourceRowHash($row['original_fields']))) {
            throw new RuntimeException('LEGACY_SOURCE_ROW_FINGERPRINT_MISMATCH');
        }
        if ($row['original_fields']['id']['kind'] !== 'D' || $this->decimalIdentity($decoded['id']) !== $row['source_id']) {
            throw new RuntimeException('LEGACY_SOURCE_ID_CHANGED');
        }
        foreach (self::DISPLAY_MAP as $display => $field) {
            $raw = $decoded[$field];
            $expected = $raw === null || ! preg_match('//u', $raw) ? null : $raw;
            if ($row['display'][$display] !== $expected || ($expected !== null && strlen($expected) > 16000)) {
                throw new RuntimeException('LEGACY_DISPLAY_PROJECTION_CHANGED');
            }
        }
        if ($row['display']['stored_status'] !== null && strlen($row['display']['stored_status']) > 255) {
            throw new RuntimeException('LEGACY_SOURCE_STATUS_TOO_LONG');
        }
        $barcode = $decoded['barcode'];
        $kind = $barcode === null ? 'null' : ($barcode === '' ? 'empty' : 'value');
        if (! in_array($row['original_fields']['barcode']['kind'], ['N', 'B'], true)
            || ($row['barcode_kind'] ?? null) !== $kind
            || ($row['barcode_sha256'] ?? null) !== ($barcode === null ? null : hash('sha256', $barcode))
            || ! is_array($row['sql_candidate_source_ids'] ?? null) || ! array_is_list($row['sql_candidate_source_ids'])
            || ! is_bool($row['barcode_ambiguous'] ?? null)) {
            throw new RuntimeException('LEGACY_BARCODE_PROJECTION_CHANGED');
        }
        $candidates = $row['sql_candidate_source_ids'];
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || ! $this->isSourceId($candidate)) {
                throw new RuntimeException('LEGACY_CANDIDATE_ID_INVALID');
            }
        }
        $sorted = $candidates;
        usort($sorted, fn (string $a, string $b): int => strlen($a) <=> strlen($b) ?: strcmp($a, $b));
        if ($sorted !== $candidates || count(array_unique($candidates, SORT_STRING)) !== count($candidates)
            || ($kind === 'value' && ! in_array($row['source_id'], $candidates, true))
            || ($row['barcode_ambiguous'] !== ($kind === 'value' && count($candidates) > 1))) {
            throw new RuntimeException('LEGACY_CANDIDATE_BINDING_MISMATCH');
        }
        return ['id' => $row['source_id'], 'barcode' => $barcode, 'candidates' => $candidates];
    }

    public function decodeField(mixed $encoded): ?string
    {
        if (! is_array($encoded) || count($encoded) !== 2 || ! isset($encoded['kind'], $encoded['base64'])
            || ! in_array($encoded['kind'], ['N', 'B', 'D'], true) || ! is_string($encoded['base64'])
            || strlen($encoded['base64']) > 1048576) { throw new RuntimeException('LEGACY_FIELD_ENCODING_INVALID'); }
        $raw = base64_decode($encoded['base64'], true);
        if ($raw === false || base64_encode($raw) !== $encoded['base64']
            || ($encoded['kind'] === 'N' && $raw !== '')) { throw new RuntimeException('LEGACY_FIELD_ENCODING_INVALID'); }
        return $encoded['kind'] === 'N' ? null : $raw;
    }

    /** Byte-identical canonical hash format used by the verified SQL capture. */
    public function sourceRowHash(array $fields): string
    {
        ksort($fields, SORT_STRING);
        $hash = hash_init('sha256');
        hash_update($hash, "IUOAMC-SQL-ROW-v1\0");
        foreach ($fields as $column => $value) {
            $raw = $this->decodeField($value);
            $canonicalValue = $value['kind'].($raw ?? '');
            hash_update($hash, pack('N2', 0, strlen((string) $column)).$column);
            hash_update($hash, pack('N2', 0, strlen($canonicalValue)).$canonicalValue);
        }
        return hash_final($hash);
    }

    /** Numeric canonical ID, without floating point conversion or platform integer truncation. */
    public function decimalIdentity(?string $raw): string
    {
        if ($raw === null || strlen($raw) > 64
            || ! preg_match('/^\+?([0-9]+)(?:\.([0-9]*))?(?:[eE]([+-]?[0-9]{1,3}))?$/D', $raw, $m)) {
            throw new RuntimeException('LEGACY_SOURCE_ID_ENCODING_INVALID');
        }
        $fraction = $m[2] ?? ''; $digits = $m[1].$fraction; $shift = (int) ($m[3] ?? 0) - strlen($fraction);
        if (abs($shift) > 64) { throw new RuntimeException('LEGACY_SOURCE_ID_ENCODING_INVALID'); }
        if ($shift < 0) {
            $tail = substr($digits, $shift);
            if (strlen($digits) <= -$shift || trim($tail, '0') !== '') { throw new RuntimeException('LEGACY_SOURCE_ID_ENCODING_INVALID'); }
            $digits = substr($digits, 0, $shift);
        } else { $digits .= str_repeat('0', $shift); }
        $digits = ltrim($digits, '0');
        if (! $this->isSourceId($digits)) { throw new RuntimeException('LEGACY_SOURCE_ID_ENCODING_INVALID'); }
        return $digits;
    }

    private function isSourceId(string $id): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{0,19}$/D', $id)
            && (strlen($id) < 20 || strcmp($id, '18446744073709551615') <= 0);
    }

    private function storageValues(array $source, int $importId): array
    {
        $values = ['import_id' => $importId, 'source_database' => self::SOURCE_DATABASE,
            'source_table' => self::SOURCE_TABLE, 'source_id' => $source['source_id'], 'snapshot_id' => self::SNAPSHOT];
        foreach (self::STORAGE_MAP as $column => $display) { $values[$column] = $source['display'][$display]; }
        return $values + ['barcode_kind' => $source['barcode_kind'], 'barcode_sha256' => $source['barcode_sha256'],
            'source_row_sha256' => $source['source_row_sha256'], 'original_fields' => $source['original_fields'],
            'sql_candidate_source_ids' => $source['sql_candidate_source_ids'],
            'candidate_count' => count($source['sql_candidate_source_ids']), 'is_ambiguous' => $source['barcode_ambiguous']];
    }

    private function storedProjection(LegacyCertificate $record): array
    {
        $keys = array_merge(['import_id', 'source_database', 'source_table', 'source_id', 'snapshot_id'],
            array_keys(self::STORAGE_MAP), ['barcode_kind', 'barcode_sha256', 'source_row_sha256', 'original_fields',
                'sql_candidate_source_ids', 'candidate_count', 'is_ambiguous']);
        $values = [];
        foreach ($keys as $key) { $values[$key] = $record->{$key}; }
        return $values;
    }

    private function readPinnedPayload(string $path, string $expected): array
    {
        if (! preg_match('/^[a-f0-9]{64}$/D', $expected) || ! str_starts_with($path, '/')) {
            throw new RuntimeException('LEGACY_IMPORT_SOURCE_PIN_REQUIRED');
        }
        $cursor = '';
        foreach (explode('/', ltrim($path, '/')) as $part) {
            if ($part === '' || $part === '.' || $part === '..') { throw new RuntimeException('LEGACY_IMPORT_PATH_INVALID'); }
            $cursor .= '/'.$part;
            if (is_link($cursor)) { throw new RuntimeException('LEGACY_IMPORT_SYMLINK_FORBIDDEN'); }
        }
        $stat = lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000 || ($stat['mode'] & 0077) !== 0
            || $stat['size'] < 1 || $stat['size'] > 64 * 1024 * 1024
            || ! in_array($stat['uid'], [0, posix_geteuid()], true)) { throw new RuntimeException('LEGACY_IMPORT_PRIVATE_FILE_REQUIRED'); }
        $handle = fopen($path, 'rb');
        if ($handle === false) { throw new RuntimeException('LEGACY_IMPORT_READ_FAILED'); }
        try {
            $open = fstat($handle);
            $bytes = stream_get_contents($handle, 64 * 1024 * 1024 + 1);
            $after = fstat($handle);
        } finally { fclose($handle); }
        foreach (['dev', 'ino', 'size', 'mtime', 'ctime'] as $key) {
            if ($stat[$key] !== $open[$key] || $open[$key] !== $after[$key]) {
                throw new RuntimeException('LEGACY_IMPORT_SOURCE_CHANGED_DURING_READ');
            }
        }
        if (! is_string($bytes) || strlen($bytes) !== $stat['size'] || ! hash_equals($expected, hash('sha256', $bytes))) {
            throw new RuntimeException('LEGACY_IMPORT_SOURCE_HASH_MISMATCH');
        }
        $payload = json_decode($bytes, true, 128, JSON_THROW_ON_ERROR);
        if (! is_array($payload)) { throw new RuntimeException('LEGACY_IMPORT_JSON_INVALID'); }
        return $payload;
    }

    private function consoleOnly(): void
    {
        if (PHP_SAPI !== 'cli' || ! app()->runningInConsole()) { throw new RuntimeException('LEGACY_CERTIFICATE_CLI_IMPORT_ONLY'); }
    }

    private function targetOnly(): void
    {
        if (DB::connection()->getDatabaseName() !== self::TARGET_DATABASE) { throw new RuntimeException('LEGACY_IMPORT_WRONG_DATABASE'); }
    }

    private function canonical(mixed $value): string
    {
        $sort = function (mixed $item) use (&$sort): mixed {
            if (! is_array($item)) { return $item; }
            if (! array_is_list($item)) { ksort($item, SORT_STRING); }
            foreach ($item as $key => $child) { $item[$key] = $sort($child); }
            return $item;
        };
        return json_encode($sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function digest(array $values): string { return hash('sha256', $this->canonical($values)); }

    private function mac(string $purpose, array $values): string
    {
        $key = config('app.key');
        if (! is_string($key) || $key === '') { throw new RuntimeException('LEGACY_REGISTRY_KEY_UNAVAILABLE'); }
        if (str_starts_with($key, 'base64:')) { $key = base64_decode(substr($key, 7), true); }
        if (! is_string($key) || strlen($key) < 32) { throw new RuntimeException('LEGACY_REGISTRY_KEY_UNAVAILABLE'); }
        $derived = hash_hmac('sha256', 'IUOAMC-LEGACY-CERTIFICATE-REGISTRY-V1|'.$purpose, $key, true);
        return hash_hmac('sha256', $this->canonical($values), $derived);
    }
}
