<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProCertificateBatchRun extends Model
{
    protected $table = 'pro_certificate_batch_runs';
    protected $guarded = ['id'];
    protected $hidden = ['plan', 'results', 'reviewed_fingerprint', 'integrity_audit_id'];

    protected function casts(): array
    {
        return ['batch_id' => 'integer', 'actor_id' => 'integer', 'lock_version' => 'integer',
            'plan' => 'array', 'results' => 'array', 'integrity_audit_id' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Certificate batch runs cannot be deleted.'));
        static::updating(static function (self $run): void {
            foreach (['record_uuid', 'batch_id', 'actor_id', 'action', 'plan', 'reviewed_fingerprint', 'created_at'] as $field) {
                if ($run->isDirty($field)) { throw new LogicException('A reviewed certificate execution plan is immutable.'); }
            }
        });
    }

    public function getDoneAttribute(): int { return count($this->results ?? []); }
    public function getTotalAttribute(): int { return count($this->plan ?? []); }
    public function batch(): BelongsTo { return $this->belongsTo(ProCertificateBatch::class, 'batch_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
