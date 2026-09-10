<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProCertificateType extends Model
{
    protected $table = 'pro_certificate_types';
    protected $guarded = ['id'];
    protected $hidden = ['created_by', 'updated_by', 'integrity_audit_id'];

    protected function casts(): array
    {
        return ['organization_id' => 'integer', 'active' => 'boolean', 'lock_version' => 'integer',
            'created_by' => 'integer', 'updated_by' => 'integer', 'integrity_audit_id' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Certificate types cannot be deleted.'));
        static::updating(static function (self $type): void {
            foreach (['organization_id', 'code', 'number_prefix', 'created_by', 'created_at'] as $field) {
                if ($type->isDirty($field)) { throw new LogicException('Certificate type identity and numbering prefix are immutable.'); }
            }
        });
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
