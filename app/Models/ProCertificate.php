<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProCertificate extends Model
{
    protected $table = 'pro_certificates';

    protected $guarded = ['id'];

    protected $hidden = [
        'recipient_name', 'statement', 'last_reason', 'issued_payload', 'pdf_path',
        'created_by', 'updated_by', 'approved_by', 'issued_by', 'revoked_by',
        'integrity_audit_id', 'creator', 'approver', 'issuer', 'integrityAudit',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer', 'lock_version' => 'integer',
            'schema_version' => 'integer', 'catalog_type_id' => 'integer', 'catalog_snapshot' => 'array',
            'created_by' => 'integer', 'updated_by' => 'integer', 'approved_by' => 'integer',
            'issued_by' => 'integer', 'revoked_by' => 'integer', 'integrity_audit_id' => 'integer',
            'achievement_date' => 'immutable_date:Y-m-d', 'expires_on' => 'immutable_date:Y-m-d',
            'issued_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime', 'last_reason' => 'encrypted', 'issued_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Certificate records cannot be deleted.'));
        static::updating(static function (self $certificate): void {
            foreach (['organization_id', 'record_uuid', 'created_by', 'created_at', 'schema_version', 'catalog_type_id', 'catalog_snapshot'] as $field) {
                if ($certificate->isDirty($field)) {
                    throw new LogicException('Certificate identity and creation history are immutable.');
                }
            }
            if ($certificate->getRawOriginal('issued_payload') !== null) {
                $immutable = [
                    'recipient_name', 'public_name', 'program_title', 'certificate_title', 'certificate_type',
                    'language', 'achievement_date', 'expires_on', 'statement', 'signatory_name', 'signatory_title',
                    'certificate_number', 'public_token', 'issued_at', 'issued_by', 'approved_at', 'approved_by',
                    'issued_payload', 'payload_sha256', 'signature', 'signing_key_id', 'pdf_path', 'pdf_sha256',
                    'schema_version', 'catalog_type_id', 'catalog_snapshot', 'specialization',
                ];
                foreach ($immutable as $field) {
                    if ($certificate->isDirty($field)) {
                        throw new LogicException('Issued certificates and their original PDF are immutable.');
                    }
                }
            }
        });
    }

    public function catalogType(): BelongsTo { return $this->belongsTo(ProCertificateType::class, 'catalog_type_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
