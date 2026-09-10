<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ProCertificate;
use App\Models\ProCertificateBatch;
use App\Models\ProCertificateBatchRun;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Batch membership and execution plans are signed, while certificate transitions retain their own transactions. */
final class ProCertificateBatchRegistry
{
    public const MAX_ROWS = 100;
    private const POLICY = [
        'submit' => ['certificates.manage', 'draft', 'review'],
        'approve' => ['certificates.review', 'review', 'approved'],
        'issue' => ['certificates.issue', 'approved', 'issued'],
    ];
    private const COMMON = [
        'batch_name', 'organization_id', 'catalog_type_id', 'language', 'program_title', 'achievement_date',
        'expires_on', 'certificate_title', 'statement', 'signatory_name', 'signatory_title', 'catalog_version',
    ];
    private const ROW = ['recipient_name', 'public_name', 'specialization'];

    public function query(User $actor): Builder
    {
        $actor = $this->actor($actor, 'certificates.view');
        return ProCertificateBatch::query()->whereIn('organization_id', (new InstitutionalAccess())->organizationIds($actor));
    }

    public function view(User $actor, int $id): ProCertificateBatch
    {
        $batch = $this->query($actor)->with(['organization', 'catalogType'])->findOrFail($id);
        if (! $this->verify($batch)) { $this->stop('batch_integrity'); }
        return $batch;
    }

    /** Reading the batch includes the exact ordered membership; no silently missing or cross-organization rows. */
    public function members(User $actor, ProCertificateBatch $batch): Collection
    {
        $batch = $this->view($actor, (int) $batch->id);
        return $this->memberRecords($actor, $batch);
    }

    public function fingerprint(User $actor, ProCertificateBatch $batch): string
    {
        $batch = $this->view($actor, (int) $batch->id);
        return $this->memberFingerprint($batch, $this->memberRecords($actor, $batch));
    }

    /** The controller must display these members with this token, rather than re-reading the token separately. */
    public function review(User $actor, ProCertificateBatch $batch): array
    {
        $batch = $this->view($actor, (int) $batch->id);
        $members = $this->memberRecords($actor, $batch);
        return ['members' => $members, 'fingerprint' => $this->memberFingerprint($batch, $members)];
    }

    public function latestRun(User $actor, ProCertificateBatch $batch): ?ProCertificateBatchRun
    {
        $batch = $this->view($actor, (int) $batch->id);
        $run = $batch->runs()->orderByDesc('id')->first();
        if ($run !== null && ! $this->verifyRun($run)) { $this->stop('batch_integrity'); }
        return $run;
    }

    public function run(User $actor, int $id): ProCertificateBatchRun
    {
        $actor = $this->actor($actor, 'certificates.view');
        $run = ProCertificateBatchRun::query()->whereHas('batch', function (Builder $query) use ($actor): void {
            $query->whereIn('organization_id', (new InstitutionalAccess())->organizationIds($actor));
        })->findOrFail($id);
        if (! $this->verifyRun($run)) { $this->stop('batch_integrity'); }
        return $run;
    }

    /** Validate every row before creating any draft; the request UUID binds the actor and normalized submitted input. */
    public function prepare(User $actor, array $common, array $rows, string $requestKey): ProCertificateBatch
    {
        $actor = $this->actor($actor, 'certificates.manage');
        [$common, $rows] = $this->normalizeInput($common, $rows);
        if (! $this->uuid($requestKey)) { $this->stop('batch_request'); }
        $digest = ProCertificateSigner::digest(['schema' => 'iuoamc-pro-certificate-batch-input-v1',
            'actor_id' => (int) $actor->id, 'common' => $common, 'rows' => $rows]);

        return $this->withLock('iuoamc:cert-prepare:'.$requestKey, function () use ($actor, $common, $rows, $requestKey, $digest): ProCertificateBatch {
            return DB::transaction(function () use ($actor, $common, $rows, $requestKey, $digest): ProCertificateBatch {
                $existing = ProCertificateBatch::query()->where('request_key', $requestKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ((int) $existing->created_by !== (int) $actor->id || ! hash_equals($existing->input_digest, $digest)) {
                        $this->stop('batch_request');
                    }
                    return $this->view($actor, (int) $existing->id);
                }
                // The type row remains locked while all defaults are validated and all drafts are inserted.
                $typeSnapshot = app(ProCertificateCatalog::class)->snapshotFor($actor, $common['catalog_type_id'], $common['organization_id']);
                if (isset($common['catalog_version']) && $common['catalog_version'] !== $typeSnapshot['version']) {
                    $this->stop('batch_stale');
                }
                $base = $common;
                unset($base['batch_name'], $base['catalog_version']);
                $registry = app(ProCertificateRegistry::class);
                $validated = [];
                foreach ($rows as $offset => $row) {
                    try { $validated[] = $registry->validateDraft($actor, array_replace($base, $row)); }
                    catch (ValidationException $error) {
                        $messages = [];
                        foreach ($error->errors() as $field => $values) { $messages['rows.'.$offset.'.'.$field] = $values; }
                        throw ValidationException::withMessages($messages);
                    }
                }
                $members = [];
                foreach ($validated as $values) { $members[] = (int) $registry->create($actor, $values)->id; }
                $batch = ProCertificateBatch::create([
                    'record_uuid' => (string) Str::uuid(), 'organization_id' => $common['organization_id'],
                    'catalog_type_id' => $common['catalog_type_id'], 'label' => $common['batch_name'],
                    'created_by' => (int) $actor->id, 'request_key' => $requestKey, 'input_digest' => $digest,
                    'member_ids' => $members, 'created_at' => now()->utc()->startOfSecond(),
                ]);
                $batch->record_hash = ProCertificateSigner::digest($this->batchSnapshot($batch));
                $audit = AuditTrail::record('pro_certificate_batch.prepared', $batch, [], $this->batchAuditValues($batch),
                    ['module' => 'pro_certificate_batches', 'organization_id' => $batch->organization_id,
                        'record_hash' => $batch->record_hash], (int) $actor->id);
                $batch->integrity_audit_id = (int) $audit->id;
                $batch->saveQuietly();
                return $batch->fresh(['organization', 'catalogType']);
            }, 3);
        });
    }

    /** A review token covers every displayed member, not only the eligible subset. */
    public function start(User $actor, int $batchId, string $action, string $fingerprint, bool $confirm): ProCertificateBatchRun
    {
        if (! isset(self::POLICY[$action])) { $this->stop('batch_action'); }
        $actor = $this->actor($actor, self::POLICY[$action][0]);
        if (! $confirm) { $this->stop('batch_confirm'); }
        if (! $this->hash($fingerprint)) { $this->stop('batch_stale'); }
        $this->view($actor, $batchId);

        return $this->withLock($this->batchLock($batchId), function () use ($actor, $batchId, $action, $fingerprint): ProCertificateBatchRun {
            return DB::transaction(function () use ($actor, $batchId, $action, $fingerprint): ProCertificateBatchRun {
                $batch = $this->query($actor)->lockForUpdate()->findOrFail($batchId);
                if (! $this->verify($batch)) { $this->stop('batch_integrity'); }
                foreach ($batch->runs()->get() as $earlierRun) {
                    if (! $this->verifyRun($earlierRun)) { $this->stop('batch_integrity'); }
                    if ($earlierRun->status === 'running') { $this->stop('batch_running'); }
                }
                $members = $this->memberRecords($actor, $batch, true);
                if (! hash_equals($fingerprint, $this->memberFingerprint($batch, $members))) { $this->stop('batch_stale'); }
                $plan = [];
                foreach ($members as $certificate) {
                    if ($certificate->status === self::POLICY[$action][1]) {
                        $plan[] = ['id' => (int) $certificate->id, 'lock_version' => $certificate->lock_version,
                            'record_hash' => $certificate->record_hash];
                    }
                }
                if ($plan === []) { $this->stop('batch_empty_action'); }
                // Never approve as a side effect of issue; the plan contains only records already approved.
                $run = ProCertificateBatchRun::create([
                    'record_uuid' => (string) Str::uuid(), 'batch_id' => $batchId, 'actor_id' => (int) $actor->id,
                    'action' => $action, 'reviewed_fingerprint' => $fingerprint, 'plan' => $plan, 'results' => [],
                    'status' => 'running', 'stop_reason' => null, 'lock_version' => 1,
                    'created_at' => now()->utc()->startOfSecond(), 'updated_at' => now()->utc()->startOfSecond(),
                ]);
                $this->appendRun($run, $actor, 'started', []);
                return $run->fresh();
            }, 3);
        });
    }

    /** Execute at most one certificate; resubmission after a committed transition recovers its signed outcome. */
    public function step(User $actor, int $runId): array
    {
        $run = $this->run($actor, $runId);
        $actor = $this->actor($actor, self::POLICY[$run->action][0]);
        abort_unless((int) $actor->id === (int) $run->actor_id, 403);

        return $this->withLock($this->batchLock((int) $run->batch_id), function () use ($actor, $runId): array {
            $run = $this->run($actor, $runId);
            $actor = $this->actor($actor, self::POLICY[$run->action][0]);
            abort_unless((int) $actor->id === (int) $run->actor_id, 403);
            if ($run->status !== 'running') { return $this->progress($run); }
            $plan = $run->plan[$run->done];
            $registry = app(ProCertificateRegistry::class);

            try {
                $certificate = $registry->query($actor)->findOrFail($plan['id']);
                if ((int) $certificate->organization_id !== (int) $run->batch->organization_id
                    || (int) $certificate->catalog_type_id !== (int) $run->batch->catalog_type_id) {
                    $this->stop('batch_integrity');
                }
                $outcome = $this->outcome($run, $plan, $certificate);
                if ($outcome === null) {
                    if ($certificate->lock_version !== $plan['lock_version']
                        || $certificate->status !== self::POLICY[$run->action][1]
                        || ! hash_equals($plan['record_hash'], (string) $certificate->record_hash)
                        || ! $registry->verify($certificate)) {
                        $this->stop('batch_stale');
                    }
                    // Do not put an outer DB transaction here: issue owns its commit and PDF failure cleanup.
                    $certificate = $registry->transition($actor, (int) $certificate->id, $plan['lock_version'], $run->action,
                        $run->action === 'issue' ? ['confirm_issue' => true] : []);
                    $outcome = $this->outcome($run, $plan, $certificate);
                    if ($outcome === null) { $this->stop('batch_integrity'); }
                }
                $run = $this->saveOutcome($actor, $run, $outcome);
            } catch (Throwable $error) {
                // A connection failure may be observed after a transition committed but before its cursor was written.
                // Only the exact planned old hash/version and signed actor/action transition can resolve that ambiguity.
                $current = $registry->query($actor)->find($plan['id']);
                $recovered = $current === null ? null : $this->outcome($run, $plan, $current);
                if ($recovered !== null) {
                    $run = $this->saveOutcome($actor, $run, $recovered);
                } else {
                    report($error);
                    $run = $this->stopRun($actor, $run, 'batch_step_failed');
                }
            }
            return $this->progress($run);
        });
    }

    public function verify(ProCertificateBatch $batch): bool
    {
        try {
            $current = ProCertificateBatch::query()->find($batch->getKey());
            if ($current === null || ! $this->validBatch($current)
                || ! hash_equals((string) $current->record_hash, ProCertificateSigner::digest($this->batchSnapshot($current)))
                || ! hash_equals((string) $batch->record_hash, (string) $current->record_hash)
                || $current->integrity_audit_id !== $batch->integrity_audit_id
                || ProCertificateSigner::canonicalJson($this->batchSnapshot($current)) !== ProCertificateSigner::canonicalJson($this->batchSnapshot($batch))) {
                return false;
            }
            $audits = AuditLog::query()->where('auditable_type', $batch->getMorphClass())->where('auditable_id', $batch->id)->get();
            if ($audits->count() !== 1) { return false; }
            $audit = $audits->first();
            return (int) $audit->id === (int) $batch->integrity_audit_id
                && (int) $audit->actor_id === (int) $batch->created_by
                && $audit->event === 'pro_certificate_batch.prepared'
                && ($audit->old_values ?? []) === []
                && ($audit->metadata['module'] ?? null) === 'pro_certificate_batches'
                && ($audit->metadata['record_hash'] ?? null) === $batch->record_hash
                && ProCertificateSigner::canonicalJson($audit->new_values) === ProCertificateSigner::canonicalJson($this->batchAuditValues($batch))
                && app(IntegrityService::class)->verifyAuditLog($audit)['valid'];
        } catch (Throwable) { return false; }
    }

    /** Validate the full run history, not an untrusted mutable cursor alone. */
    public function verifyRun(ProCertificateBatchRun $run): bool
    {
        try {
            $current = ProCertificateBatchRun::query()->find($run->getKey());
            if ($current === null || ! $this->validRun($current)
                || ! hash_equals((string) $current->record_hash, ProCertificateSigner::digest($this->runSnapshot($current)))
                || ! hash_equals((string) $run->record_hash, (string) $current->record_hash)
                || $current->integrity_audit_id !== $run->integrity_audit_id
                || ProCertificateSigner::canonicalJson($this->runSnapshot($run)) !== ProCertificateSigner::canonicalJson($this->runSnapshot($current))) {
                return false;
            }
            $batch = $run->batch;
            if ($batch === null || ! $this->verify($batch)) { return false; }
            $planIds = array_column($run->plan, 'id');
            if (array_values(array_intersect($batch->member_ids, $planIds)) !== $planIds) { return false; }
            $audits = AuditLog::query()->where('auditable_type', $run->getMorphClass())
                ->where('auditable_id', $run->id)->orderBy('sequence_number')->orderBy('id')->get();
            if ($audits->count() !== $run->lock_version) { return false; }
            $prior = null;
            foreach ($audits as $offset => $audit) {
                $values = $audit->new_values ?? [];
                if (! app(IntegrityService::class)->verifyAuditLog($audit)['valid']
                    || (int) $audit->actor_id !== (int) $run->actor_id
                    || ($values['record_uuid'] ?? null) !== $run->record_uuid
                    || ($values['batch_id'] ?? null) !== (int) $run->batch_id
                    || ($values['actor_id'] ?? null) !== (int) $run->actor_id
                    || ($values['action'] ?? null) !== $run->action
                    || ($values['plan_digest'] ?? null) !== ProCertificateSigner::digest($run->plan)
                    || ($values['reviewed_fingerprint'] ?? null) !== $run->reviewed_fingerprint
                    || ($values['lock_version'] ?? null) !== $offset + 1
                    || ($audit->metadata['module'] ?? null) !== 'pro_certificate_batches'
                    || ($audit->metadata['record_hash'] ?? null) !== ($values['record_hash'] ?? null)
                    || ! $this->validRunAuditTransition($audit, $prior)) {
                    return false;
                }
                $prior = $audit;
            }
            return $prior !== null && (int) $prior->id === (int) $run->integrity_audit_id
                && ProCertificateSigner::canonicalJson($prior->new_values) === ProCertificateSigner::canonicalJson($this->runAuditValues($run));
        } catch (Throwable) { return false; }
    }

    private function actor(User $actor, string $permission): User
    {
        abort_unless($actor->exists && (int) $actor->id > 0, 403);
        $fresh = User::query()->whereKey($actor->id)->where('status', 'active')->first();
        abort_unless($fresh !== null, 403);
        app(ProCertificateRegistry::class)->requirePermission($fresh, $permission);
        return $fresh;
    }

    private function memberRecords(User $actor, ProCertificateBatch $batch, bool $lock = false): Collection
    {
        $actor = $this->actor($actor, 'certificates.view');
        $registry = app(ProCertificateRegistry::class);
        $query = $registry->query($actor)->whereIn('id', $batch->member_ids)->orderBy('id');
        if ($lock) { $query->lockForUpdate(); }
        $found = $query->get()->keyBy('id');
        if ($found->count() !== count($batch->member_ids)) { $this->stop('batch_integrity'); }
        $members = collect();
        foreach ($batch->member_ids as $id) {
            $certificate = $found->get($id);
            if ($certificate === null || (int) $certificate->organization_id !== (int) $batch->organization_id
                || (int) $certificate->catalog_type_id !== (int) $batch->catalog_type_id || ! $registry->verify($certificate)) {
                $this->stop('batch_integrity');
            }
            $members->push($certificate);
        }
        return $members;
    }

    private function memberFingerprint(ProCertificateBatch $batch, Collection $members): string
    {
        return ProCertificateSigner::digest(['schema' => 'iuoamc-pro-certificate-batch-review-v1',
            'batch_id' => (int) $batch->id, 'batch_hash' => $batch->record_hash,
            'members' => $members->map(static fn (ProCertificate $certificate): array => [
                'id' => (int) $certificate->id, 'lock_version' => $certificate->lock_version,
                'status' => $certificate->status, 'record_hash' => $certificate->record_hash,
            ])->all()]);
    }

    private function normalizeInput(array $common, array $rows): array
    {
        if (array_diff(array_keys($common), self::COMMON) !== []
            || ! array_is_list($rows) || count($rows) < 1 || count($rows) > self::MAX_ROWS) {
            $this->stop('batch_input');
        }
        foreach ($common as $field => $value) { if (is_string($value)) { $common[$field] = trim($value); } }
        foreach (['certificate_title', 'statement', 'signatory_name', 'signatory_title'] as $field) {
            if (($common[$field] ?? null) === '' || ($common[$field] ?? null) === null) { unset($common[$field]); }
        }
        if (! array_key_exists('expires_on', $common) || $common['expires_on'] === '') { $common['expires_on'] = null; }
        $validated = Validator::make($common, [
            'batch_name' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || preg_match('//u', $value) !== 1 || preg_match('/[\x00-\x1F\x7F]/u', $value) !== 0) {
                    $fail(trans('certificate_catalog.errors.batch_input'));
                }
            }],
            'organization_id' => ['required', 'integer', 'min:1'], 'catalog_type_id' => ['required', 'integer', 'min:1'],
            'catalog_version' => ['sometimes', 'integer', 'min:1'],
        ])->validate();
        $common['organization_id'] = (int) $validated['organization_id'];
        $common['catalog_type_id'] = (int) $validated['catalog_type_id'];
        if (isset($validated['catalog_version'])) { $common['catalog_version'] = (int) $validated['catalog_version']; }
        $seen = [];
        foreach ($rows as &$row) {
            if (! is_array($row) || array_diff(array_keys($row), self::ROW) !== []) { $this->stop('batch_input'); }
            foreach ($row as $field => $value) { if (is_string($value)) { $row[$field] = trim($value); } }
            if (! array_key_exists('specialization', $row) || $row['specialization'] === '') { $row['specialization'] = null; }
            $digest = ProCertificateSigner::digest($row);
            if (isset($seen[$digest])) { $this->stop('batch_duplicate'); }
            $seen[$digest] = true;
        }
        unset($row);
        return [$common, $rows];
    }

    private function outcome(ProCertificateBatchRun $run, array $plan, ProCertificate $certificate): ?array
    {
        if ((int) $certificate->id !== $plan['id'] || $certificate->lock_version !== $plan['lock_version'] + 1
            || $certificate->status !== self::POLICY[$run->action][2] || (int) $certificate->updated_by !== (int) $run->actor_id
            || ! app(ProCertificateRegistry::class)->verify($certificate)) { return null; }
        $audit = AuditLog::query()->find($certificate->integrity_audit_id);
        $start = AuditLog::query()->where('auditable_type', $run->getMorphClass())->where('auditable_id', $run->id)
            ->where('event', 'pro_certificate_batch_run.started')->first();
        if ($audit === null || $start === null || $audit->event !== 'pro_certificate.'.$run->action
            || (int) $audit->actor_id !== (int) $run->actor_id
            || (int) $audit->auditable_id !== (int) $certificate->id || $audit->auditable_type !== $certificate->getMorphClass()
            || (int) $audit->sequence_number <= (int) $start->sequence_number
            || ($audit->old_values['lock_version'] ?? null) !== $plan['lock_version']
            || ($audit->old_values['record_hash'] ?? null) !== $plan['record_hash']
            || ($audit->old_values['status'] ?? null) !== self::POLICY[$run->action][1]
            || ($audit->new_values['record_hash'] ?? null) !== $certificate->record_hash
            || ! app(IntegrityService::class)->verifyAuditLog($audit)['valid']) { return null; }
        return ['id' => (int) $certificate->id, 'lock_version' => $certificate->lock_version,
            'record_hash' => $certificate->record_hash, 'integrity_audit_id' => (int) $certificate->integrity_audit_id];
    }

    private function saveOutcome(User $actor, ProCertificateBatchRun $previous, array $outcome): ProCertificateBatchRun
    {
        return DB::transaction(function () use ($actor, $previous, $outcome): ProCertificateBatchRun {
            $run = ProCertificateBatchRun::query()->lockForUpdate()->findOrFail($previous->id);
            if (! $this->verifyRun($run)) { $this->stop('batch_integrity'); }
            // A successful cursor write whose acknowledgement was lost is itself idempotent.
            if ($run->done === $previous->done + 1
                && ProCertificateSigner::canonicalJson($run->results[$previous->done]) === ProCertificateSigner::canonicalJson($outcome)) {
                return $run;
            }
            if ($run->status !== 'running' || $run->lock_version !== $previous->lock_version || $run->done !== $previous->done) {
                $this->stop('batch_stale');
            }
            $old = $this->runAuditValues($run);
            $run->results = [...$run->results, $outcome];
            $run->status = $run->done === $run->total ? 'completed' : 'running';
            $run->lock_version++;
            $run->updated_at = now()->utc()->startOfSecond();
            $run->save();
            $this->appendRun($run, $actor, 'progressed', $old);
            return $run->fresh();
        }, 3);
    }

    private function stopRun(User $actor, ProCertificateBatchRun $previous, string $reason): ProCertificateBatchRun
    {
        return DB::transaction(function () use ($actor, $previous, $reason): ProCertificateBatchRun {
            $run = ProCertificateBatchRun::query()->lockForUpdate()->findOrFail($previous->id);
            if (! $this->verifyRun($run) || $run->lock_version !== $previous->lock_version) { $this->stop('batch_integrity'); }
            if ($run->status !== 'running') { return $run; }
            $old = $this->runAuditValues($run);
            $run->status = 'stopped';
            $run->stop_reason = $reason;
            $run->lock_version++;
            $run->updated_at = now()->utc()->startOfSecond();
            $run->save();
            $this->appendRun($run, $actor, 'stopped', $old);
            return $run->fresh();
        }, 3);
    }

    private function appendRun(ProCertificateBatchRun $run, User $actor, string $action, array $old): void
    {
        $run->record_hash = ProCertificateSigner::digest($this->runSnapshot($run));
        $audit = AuditTrail::record('pro_certificate_batch_run.'.$action, $run, $old, $this->runAuditValues($run),
            ['module' => 'pro_certificate_batches', 'organization_id' => (int) $run->batch->organization_id,
                'record_hash' => $run->record_hash], (int) $actor->id);
        $run->integrity_audit_id = (int) $audit->id;
        $timestamps = $run->timestamps;
        try { $run->timestamps = false; $run->saveQuietly(); }
        finally { $run->timestamps = $timestamps; }
    }

    private function progress(ProCertificateBatchRun $run): array
    {
        return ['run_id' => (int) $run->id, 'done' => $run->done, 'total' => $run->total,
            'completed' => $run->status === 'completed', 'stopped' => $run->status === 'stopped',
            'message' => $run->status === 'stopped' ? trans('certificate_catalog.errors.batch_step_failed') : null];
    }

    private function batchSnapshot(ProCertificateBatch $batch): array
    {
        return ['schema' => 'iuoamc-pro-certificate-batch-v1', 'id' => (int) $batch->id,
            'record_uuid' => $batch->record_uuid, 'organization_id' => $batch->organization_id,
            'catalog_type_id' => $batch->catalog_type_id, 'label' => $batch->label, 'created_by' => $batch->created_by,
            'request_key' => $batch->request_key, 'input_digest' => $batch->input_digest, 'member_ids' => $batch->member_ids,
            'created_at' => $this->iso($batch->created_at)];
    }

    private function batchAuditValues(ProCertificateBatch $batch): array
    {
        return ['record_uuid' => $batch->record_uuid, 'organization_id' => $batch->organization_id,
            'catalog_type_id' => $batch->catalog_type_id, 'created_by' => $batch->created_by,
            'input_digest' => $batch->input_digest, 'member_ids' => $batch->member_ids, 'record_hash' => $batch->record_hash];
    }

    private function runSnapshot(ProCertificateBatchRun $run): array
    {
        return ['schema' => 'iuoamc-pro-certificate-batch-run-v1', 'id' => (int) $run->id,
            'record_uuid' => $run->record_uuid, 'batch_id' => $run->batch_id, 'actor_id' => $run->actor_id,
            'action' => $run->action, 'reviewed_fingerprint' => $run->reviewed_fingerprint,
            'plan' => $run->plan, 'results' => $run->results, 'status' => $run->status, 'stop_reason' => $run->stop_reason,
            'lock_version' => $run->lock_version, 'created_at' => $this->iso($run->created_at), 'updated_at' => $this->iso($run->updated_at)];
    }

    private function runAuditValues(ProCertificateBatchRun $run): array
    {
        return ['record_uuid' => $run->record_uuid, 'batch_id' => $run->batch_id, 'actor_id' => $run->actor_id,
            'action' => $run->action, 'reviewed_fingerprint' => $run->reviewed_fingerprint,
            'plan_digest' => ProCertificateSigner::digest($run->plan), 'results' => $run->results,
            'status' => $run->status, 'stop_reason' => $run->stop_reason, 'lock_version' => $run->lock_version,
            'record_hash' => $run->record_hash];
    }

    private function validBatch(ProCertificateBatch $batch): bool
    {
        return $this->uuid((string) $batch->record_uuid) && $this->uuid((string) $batch->request_key)
            && $batch->organization_id > 0 && $batch->catalog_type_id > 0 && $batch->created_by > 0
            && $batch->created_at !== null && $this->hash((string) $batch->input_digest) && $this->hash((string) $batch->record_hash)
            && is_string($batch->label) && trim($batch->label) !== '' && mb_strlen($batch->label) <= 120
            && is_array($batch->member_ids) && array_is_list($batch->member_ids)
            && count($batch->member_ids) >= 1 && count($batch->member_ids) <= self::MAX_ROWS
            && count(array_unique($batch->member_ids, SORT_REGULAR)) === count($batch->member_ids)
            && count(array_filter($batch->member_ids, static fn ($id): bool => is_int($id) && $id > 0)) === count($batch->member_ids);
    }

    private function validRun(ProCertificateBatchRun $run): bool
    {
        if (! $this->uuid((string) $run->record_uuid) || $run->batch_id < 1 || $run->actor_id < 1 || $run->lock_version < 1
            || ! isset(self::POLICY[$run->action]) || ! $this->hash((string) $run->reviewed_fingerprint)
            || ! $this->hash((string) $run->record_hash) || $run->created_at === null || $run->updated_at === null
            || ! is_array($run->plan) || ! array_is_list($run->plan) || count($run->plan) < 1 || count($run->plan) > self::MAX_ROWS
            || ! is_array($run->results) || ! array_is_list($run->results) || count($run->results) > count($run->plan)
            || ! in_array($run->status, ['running', 'completed', 'stopped'], true)
            || ($run->status === 'completed') !== ($run->done === $run->total)
            || ($run->status === 'stopped' ? $run->stop_reason !== 'batch_step_failed' : $run->stop_reason !== null)) {
            return false;
        }
        $seen = [];
        foreach ($run->plan as $offset => $row) {
            if (! is_array($row) || array_keys($row) !== ['id', 'lock_version', 'record_hash']
                || ! is_int($row['id']) || $row['id'] < 1 || isset($seen[$row['id']])
                || ! is_int($row['lock_version']) || $row['lock_version'] < 1 || ! $this->hash((string) $row['record_hash'])) { return false; }
            $seen[$row['id']] = true;
            if (isset($run->results[$offset])) {
                $result = $run->results[$offset];
                if (! is_array($result) || array_keys($result) !== ['id', 'lock_version', 'record_hash', 'integrity_audit_id']
                    || $result['id'] !== $row['id'] || $result['lock_version'] !== $row['lock_version'] + 1
                    || ! $this->hash((string) $result['record_hash'])
                    || ! is_int($result['integrity_audit_id']) || $result['integrity_audit_id'] < 1) { return false; }
            }
        }
        return true;
    }

    private function validRunAuditTransition(AuditLog $audit, ?AuditLog $prior): bool
    {
        $new = $audit->new_values ?? [];
        if ($prior === null) {
            return $audit->event === 'pro_certificate_batch_run.started' && ($audit->old_values ?? []) === []
                && ($new['status'] ?? null) === 'running' && ($new['results'] ?? null) === [] && ($new['stop_reason'] ?? null) === null;
        }
        $old = $audit->old_values ?? [];
        if (ProCertificateSigner::canonicalJson($old) !== ProCertificateSigner::canonicalJson($prior->new_values)
            || ($old['status'] ?? null) !== 'running') { return false; }
        if ($audit->event === 'pro_certificate_batch_run.stopped') {
            return ($new['status'] ?? null) === 'stopped' && ($new['stop_reason'] ?? null) === 'batch_step_failed'
                && ($new['results'] ?? null) === ($old['results'] ?? null);
        }
        return $audit->event === 'pro_certificate_batch_run.progressed'
            && in_array($new['status'] ?? null, ['running', 'completed'], true) && ($new['stop_reason'] ?? null) === null
            && is_array($old['results'] ?? null) && is_array($new['results'] ?? null)
            && count($new['results']) === count($old['results']) + 1
            && array_slice($new['results'], 0, count($old['results'])) === $old['results'];
    }

    private function withLock(string $name, \Closure $operation): mixed
    {
        $connection = DB::connection();
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$name], false);
        if ((int) ($lock->acquired ?? 0) !== 1) { $this->stop('batch_busy'); }
        try { return $operation(); }
        finally {
            try { $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$name], false); }
            catch (Throwable $error) { report($error); }
        }
    }

    private function batchLock(int $id): string { return 'iuoamc:cert-batch:'.$id; }
    private function hash(string $value): bool { return preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1; }
    private function uuid(string $value): bool { return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $value) === 1; }
    private function iso(?DateTimeInterface $date): ?string { return $date === null ? null : \Carbon\CarbonImmutable::instance($date)->utc()->format('Y-m-d\TH:i:sP'); }
    private function stop(string $reason): never { throw ValidationException::withMessages(['batch' => trans('certificate_catalog.errors.'.$reason)]); }
}
