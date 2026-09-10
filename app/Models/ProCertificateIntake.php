<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ProCertificateIntake extends Model
{
    protected $table = 'pro_certificate_intakes';
    protected $guarded = ['id'];
    protected $hidden = ['source_snapshot', 'response_payload', 'token_hash', 'token_encrypted',
        'created_by', 'reviewed_by', 'integrity_audit_id', 'creator', 'reviewer', 'integrityAudit'];

    protected function casts(): array
    {
        return ['organization_id' => 'integer', 'created_by' => 'integer', 'reviewed_by' => 'integer',
            'integrity_audit_id' => 'integer', 'source_snapshot' => 'encrypted:array',
            'response_payload' => 'encrypted:array', 'token_encrypted' => 'encrypted',
            'expires_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Intake history requires a controlled retention operation.'));
        static::updating(static function (self $record): void {
            foreach (['organization_id', 'source_system', 'source_id', 'source_certificate_number',
                'source_snapshot', 'created_by', 'created_at'] as $field) {
                if ($record->isDirty($field)) { throw new LogicException('Imported source identity is immutable.'); }
            }
            if ($record->getRawOriginal('response_payload') !== null
                && ($record->isDirty('response_payload') || $record->isDirty('submitted_at'))) {
                throw new LogicException('A submitted response is immutable.');
            }
        });
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
