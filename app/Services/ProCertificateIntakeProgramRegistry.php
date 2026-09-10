<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\{AuditLog, Organization, ProCertificateIntake, ProCertificateIntakeProgram, ProCertificateIntakeResponse, User};
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Validator};
use Throwable;

final class ProCertificateIntakeProgramRegistry
{
    public const RESPONSE_FIELDS = ['name_ar', 'name_en', 'email', 'phone', 'country', 'specialization', 'notes', 'confirmation', 'registration_number'];

    public function query(User $actor): Builder
    {
        app(ProCertificateIntakeRegistry::class)->requirePermission($actor);
        return ProCertificateIntakeProgram::query()->whereIn('organization_id', app(InstitutionalAccess::class)->organizationIds($actor));
    }

    public function ensureProgram(User $actor, int $organizationId, array $metadata): ProCertificateIntakeProgram
    {
        app(ProCertificateIntakeRegistry::class)->requirePermission($actor);
        $data = Validator::make($metadata, ['code' => ['required', 'string', 'max:80', 'regex:/\A[A-Z0-9][A-Z0-9._-]*\z/D'],
            'program_name_ar' => ['required', 'string', 'max:255'], 'program_name_en' => ['required', 'string', 'max:255'],
            'program_name_fr' => ['nullable', 'string', 'max:255']])->validate();
        $data['program_name_fr'] = $data['program_name_fr'] ?? null;
        return DB::transaction(function () use ($actor, $organizationId, $data): ProCertificateIntakeProgram {
            $organization = Organization::query()->lockForUpdate()->findOrFail($organizationId);
            app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
            abort_unless($organization->status === 'active', 409, __('certificate_intake.errors.organization'));
            $existing = ProCertificateIntakeProgram::query()->where('code', $data['code'])->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless((int) $existing->organization_id === $organizationId && $this->verifyProgram($existing), 409, __('certificate_intake.errors.source'));
                foreach ($data as $field => $value) { abort_unless($existing->{$field} === $value, 409, __('certificate_intake.errors.source')); }
                return $existing;
            }
            $program = ProCertificateIntakeProgram::create($data + ['organization_id' => $organizationId,
                'status' => 'open', 'created_by' => (int) $actor->id]);
            $this->append($program, 'program.created', [], $actor);
            return $program->fresh('organization');
        }, 3);
    }

    public function responses(User $actor, int $programId): Builder
    {
        $program = $this->query($actor)->findOrFail($programId);
        return ProCertificateIntakeResponse::query()->where('program_id', $program->id);
    }

    public function responseQuery(User $actor): Builder
    {
        $programs = $this->query($actor)->select('id');
        return ProCertificateIntakeResponse::query()->whereIn('program_id', $programs);
    }

    public function candidates(User $actor, ProCertificateIntakeProgram $program): Collection
    {
        $this->query($actor)->findOrFail($program->id);
        return app(ProCertificateIntakeRegistry::class)->query($actor)->where('organization_id', $program->organization_id)
            ->orderBy('id')->get()->filter(fn (ProCertificateIntake $intake): bool =>
                ($intake->source_snapshot['program_code'] ?? null) === $program->code
                && app(ProCertificateIntakeRegistry::class)->verify($intake))->values();
    }

    public function publicProgram(string $code): ?ProCertificateIntakeProgram
    {
        if (!$this->validCode($code)) { return null; }
        $programs = ProCertificateIntakeProgram::query()->with('organization')->where('code', $code)
            ->where('status', 'open')->whereHas('organization', fn ($q) => $q->where('status', 'active'))->limit(2)->get();
        if ($programs->count() !== 1) { return null; }
        $program = $programs->first();
        return $this->verifyProgram($program) ? $program : null;
    }

    public function sharedUrl(ProCertificateIntakeProgram $program, string $locale): ?string
    {
        if (!in_array($locale, ['ar', 'en', 'fr'], true) || !$this->validCode($program->code)
            || !$this->verifyProgram($program)) { return null; }
        return 'https://iuoamc.pro/'.$locale.'/certificate-data/program/'.$program->code;
    }

    public function responseValidator(array $data): \Illuminate\Validation\Validator
    {
        $rules = app(ProCertificateIntakeRegistry::class)->responseRules();
        $rules['registration_number'] = ['nullable', 'string', 'max:80', 'not_regex:/[\x00-\x1F\x7F\x{202A}-\x{202E}\x{2066}-\x{2069}]/u'];
        $messages = [];
        foreach (['required', 'string', 'max', 'email', 'accepted', 'not_regex'] as $rule) { $messages[$rule] = __('certificate_intake.validation.'.$rule); }
        $attributes = [];
        foreach (self::RESPONSE_FIELDS as $field) { $attributes[$field] = __('certificate_intake.fields.'.$field); }
        return Validator::make($data, $rules, $messages, $attributes);
    }

    /** The controller binds nonce to this program and browser session; the database makes POST retries idempotent. */
    public function submit(string $code, string $nonce, array $data): void
    {
        abort_unless($this->validCode($code) && preg_match('/\A[a-f0-9]{64}\z/D', $nonce) === 1, 404);
        $data = array_intersect_key($data, array_flip(self::RESPONSE_FIELDS));
        $data = $this->responseValidator($data)->validate();
        foreach (self::RESPONSE_FIELDS as $field) { $data[$field] = $data[$field] ?? null; }
        $data['confirmation'] = true;
        DB::transaction(function () use ($code, $nonce, $data): void {
            $candidate = $this->publicProgram($code);
            abort_if($candidate === null, 404);
            $organization = Organization::query()->lockForUpdate()->find($candidate->organization_id);
            abort_if($organization === null || $organization->status !== 'active', 404);
            $program = ProCertificateIntakeProgram::query()->lockForUpdate()->find($candidate->id);
            abort_if($program === null || $program->status !== 'open' || !$this->verifyProgram($program), 404);
            $nonceHash = hash('sha256', $nonce);
            $existing = ProCertificateIntakeResponse::query()->where('program_id', $program->id)->where('nonce_hash', $nonceHash)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($this->verifyResponse($existing)
                    && $this->json($existing->response_payload) === $this->json($data), 409, __('certificate_intake.errors.nonce'));
                return;
            }
            $response = ProCertificateIntakeResponse::create(['program_id' => (int) $program->id,
                'response_payload' => $data, 'nonce_hash' => $nonceHash, 'status' => 'submitted']);
            $this->append($response, 'response.submitted', [], null);
        }, 3);
    }

    public function review(User $actor, int $responseId, int $intakeId): ProCertificateIntakeResponse
    {
        return DB::transaction(function () use ($actor, $responseId, $intakeId): ProCertificateIntakeResponse {
            app(ProCertificateIntakeRegistry::class)->requirePermission($actor, 'certificates.review');
            [$program, $response] = $this->lockedResponse($actor, $responseId);
            abort_unless($response->status === 'submitted', 409, __('certificate_intake.errors.transition'));
            $intake = app(ProCertificateIntakeRegistry::class)->query($actor)->where('organization_id', $program->organization_id)
                ->lockForUpdate()->findOrFail($intakeId);
            abort_unless(app(ProCertificateIntakeRegistry::class)->verify($intake)
                && ($intake->source_snapshot['program_code'] ?? null) === $program->code, 409, __('certificate_intake.errors.match'));
            abort_if(ProCertificateIntakeResponse::query()->where('program_id', $program->id)
                ->where('matched_intake_id', $intakeId)->exists(), 409, __('certificate_intake.errors.match'));
            $old = $this->auditValues($response);
            $response->status = 'reviewed';
            $response->matched_intake_id = $intakeId;
            $response->reviewed_by = (int) $actor->id;
            $response->reviewed_at = now()->utc()->startOfSecond();
            $response->save();
            $this->append($response, 'response.reviewed', $old, $actor);
            return $response->fresh(['program', 'matchedIntake']);
        }, 3);
    }

    public function dismiss(User $actor, int $responseId): ProCertificateIntakeResponse
    {
        return DB::transaction(function () use ($actor, $responseId): ProCertificateIntakeResponse {
            app(ProCertificateIntakeRegistry::class)->requirePermission($actor, 'certificates.review');
            [$program, $response] = $this->lockedResponse($actor, $responseId);
            abort_unless($response->status === 'submitted', 409, __('certificate_intake.errors.transition'));
            $old = $this->auditValues($response);
            $response->status = 'dismissed';
            $response->reviewed_by = (int) $actor->id;
            $response->reviewed_at = now()->utc()->startOfSecond();
            $response->save();
            $this->append($response, 'response.dismissed', $old, $actor);
            return $response->fresh('program');
        }, 3);
    }

    public function setStatus(User $actor, int $programId, string $status): ProCertificateIntakeProgram
    {
        abort_unless(in_array($status, ['open', 'closed'], true), 422, __('certificate_intake.errors.transition'));
        return DB::transaction(function () use ($actor, $programId, $status): ProCertificateIntakeProgram {
            $program = $this->lockedProgram($actor, $programId);
            if ($program->status === $status) { return $program; }
            $old = $this->auditValues($program);
            $program->status = $status;
            $program->save();
            $this->append($program, 'program.'.$status, $old, $actor);
            return $program->fresh('organization');
        }, 3);
    }

    public function verifyProgram(ProCertificateIntakeProgram $program): bool { return $this->verifyModel($program); }
    public function verifyResponse(ProCertificateIntakeResponse $response): bool
    {
        $program = ProCertificateIntakeProgram::query()->find($response->program_id);
        if ($program === null || !$this->verifyProgram($program) || !$this->verifyModel($response)) { return false; }
        if ($response->status !== 'reviewed') { return $response->matched_intake_id === null; }
        $source = ProCertificateIntake::query()->find($response->matched_intake_id);
        return $source !== null && (int) $source->organization_id === (int) $program->organization_id
            && app(ProCertificateIntakeRegistry::class)->verify($source)
            && ($source->source_snapshot['program_code'] ?? null) === $program->code;
    }
    public function verify(ProCertificateIntakeProgram $program): bool { return $this->verifyProgram($program); }

    private function lockedProgram(User $actor, int $programId): ProCertificateIntakeProgram
    {
        $candidate = $this->query($actor)->findOrFail($programId);
        $organization = Organization::query()->lockForUpdate()->findOrFail($candidate->organization_id);
        app(InstitutionalAccess::class)->authorizeOrganization($actor, $organization);
        abort_unless($organization->status === 'active', 409, __('certificate_intake.errors.organization'));
        $program = $this->query($actor)->lockForUpdate()->findOrFail($programId);
        abort_unless($this->verifyProgram($program), 409, __('certificate_intake.errors.integrity'));
        return $program;
    }

    private function lockedResponse(User $actor, int $responseId): array
    {
        $candidate = $this->responseQuery($actor)->findOrFail($responseId);
        $program = $this->lockedProgram($actor, (int) $candidate->program_id);
        $response = ProCertificateIntakeResponse::query()->where('program_id', $program->id)->lockForUpdate()->findOrFail($responseId);
        abort_unless($this->verifyResponse($response), 409, __('certificate_intake.errors.integrity'));
        return [$program, $response];
    }

    private function verifyModel(Model $record): bool
    {
        try {
            if (!$record->exists || !$record->integrity_audit_id) { return false; }
            $current = $record->newQuery()->find($record->id);
            if ($current === null || $this->digest($current) !== $this->digest($record)
                || (int) $current->integrity_audit_id !== (int) $record->integrity_audit_id) { return false; }
            $audits = AuditLog::query()->where('auditable_type', $record->getMorphClass())->where('auditable_id', $record->id)
                ->orderBy('sequence_number')->orderBy('id')->get();
            if ($audits->isEmpty() || (int) $audits->last()->id !== (int) $record->integrity_audit_id) { return false; }
            $prior = null;
            foreach ($audits as $audit) {
                if (!(app(IntegrityService::class)->verifyAuditLog($audit)['valid'] ?? false)
                    || !$this->validTransition($audit, $prior, $record instanceof ProCertificateIntakeProgram)
                    || ($audit->metadata['module'] ?? null) !== 'certificate_data_program'
                    || ($audit->metadata['credential_issued'] ?? null) !== false
                    || $this->json($audit->old_values ?? []) !== $this->json($prior?->new_values ?? [])) { return false; }
                $prior = $audit;
            }
            return $this->json($audits->last()->new_values ?? []) === $this->json($this->auditValues($current));
        } catch (Throwable) { return false; }
    }

    private function validTransition(AuditLog $audit, ?AuditLog $prior, bool $isProgram): bool
    {
        $next = $audit->new_values['status'] ?? null;
        $before = $prior?->new_values['status'] ?? null;
        if ($prior === null) {
            return $isProgram ? $audit->event === 'pro_certificate_intake.program.created' && $next === 'open'
                : $audit->event === 'pro_certificate_intake.response.submitted' && $next === 'submitted';
        }
        if ($isProgram) {
            return ($audit->event === 'pro_certificate_intake.program.open' && $before === 'closed' && $next === 'open')
                || ($audit->event === 'pro_certificate_intake.program.closed' && $before === 'open' && $next === 'closed');
        }
        return $before === 'submitted'
            && (($audit->event === 'pro_certificate_intake.response.reviewed' && $next === 'reviewed')
                || ($audit->event === 'pro_certificate_intake.response.dismissed' && $next === 'dismissed'));
    }

    private function auditValues(Model $record): array
    {
        return ['record_id' => (int) $record->id, 'status' => $record->status, 'state_sha256' => $this->digest($record)];
    }
    private function digest(Model $record): string
    {
        $values = $record->getAttributes();
        unset($values['integrity_audit_id']);
        return hash('sha256', $this->json($values));
    }
    private function json(array $value): string { return ProCertificateSigner::canonicalJson($value); }
    private function validCode(string $code): bool { return preg_match('/\A[A-Z0-9][A-Z0-9._-]{0,79}\z/D', $code) === 1; }
    private function append(Model $record, string $event, array $old, ?User $actor): void
    {
        $record->refresh();
        $audit = AuditTrail::record('pro_certificate_intake.'.$event, $record, $old, $this->auditValues($record),
            ['module' => 'certificate_data_program', 'actor_kind' => $actor === null ? 'program_respondent' : 'administrator',
                'credential_issued' => false], $actor === null ? null : (int) $actor->id);
        $record->integrity_audit_id = (int) $audit->id;
        $timestamps = $record->timestamps;
        try { $record->timestamps = false; $record->saveQuietly(); }
        finally { $record->timestamps = $timestamps; }
    }
}
