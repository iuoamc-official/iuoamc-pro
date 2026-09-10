<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class WicpRecord extends Model
{
    protected $table = 'wicp_records';
    protected $guarded = ['id'];
    protected $hidden = ['last_reason', 'created_by', 'updated_by', 'revoked_by', 'integrity_audit_id', 'certificate', 'integrityAudit'];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer', 'authority_organization_id' => 'integer',
            'parent_id' => 'integer', 'certificate_id' => 'integer', 'lock_version' => 'integer',
            'created_by' => 'integer', 'updated_by' => 'integer', 'revoked_by' => 'integer', 'integrity_audit_id' => 'integer',
            'authority_snapshot' => 'array', 'registration_payload' => 'array', 'signed_payload' => 'array',
            'registered_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime', 'last_reason' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('WICP registered references cannot be deleted.'));
        static::updating(static function (self $record): void {
            foreach ([
                'record_uuid', 'reference', 'public_token', 'kind', 'organization_id', 'authority_organization_id',
                'authority_snapshot', 'parent_id', 'certificate_id', 'program_code', 'program_title', 'program_version',
                'source_record_uuid', 'source_number', 'source_payload_sha256', 'source_pdf_sha256',
                'registration_payload', 'binding_sha256', 'registered_at', 'created_by', 'created_at',
            ] as $field) {
                if ($record->isDirty($field)) { throw new LogicException('WICP registration identity and content binding are immutable.'); }
            }
            if ($record->isDirty('status') && ! ($record->getRawOriginal('status') === 'registered' && $record->status === 'revoked')) {
                throw new LogicException('Only an explicit registered-to-revoked WICP transition is supported.');
            }
            if ($record->getRawOriginal('status') === 'revoked' && $record->isDirty()) {
                throw new LogicException('A revoked WICP record is immutable.');
            }
        });
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function authority(): BelongsTo { return $this->belongsTo(Organization::class, 'authority_organization_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function certificate(): BelongsTo { return $this->belongsTo(ProCertificate::class, 'certificate_id'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
