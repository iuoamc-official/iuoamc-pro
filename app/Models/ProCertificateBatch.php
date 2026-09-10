<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProCertificateBatch extends Model
{
    protected $table = 'pro_certificate_batches';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $hidden = ['request_key', 'input_digest', 'member_ids', 'integrity_audit_id'];

    protected function casts(): array
    {
        return ['organization_id' => 'integer', 'catalog_type_id' => 'integer', 'created_by' => 'integer',
            'member_ids' => 'array', 'integrity_audit_id' => 'integer', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Certificate batches cannot be deleted.'));
        static::updating(static fn () => throw new LogicException('Prepared batch membership and input are immutable.'));
    }

    public function getCountAttribute(): int { return count($this->member_ids ?? []); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function catalogType(): BelongsTo { return $this->belongsTo(ProCertificateType::class, 'catalog_type_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function runs(): HasMany { return $this->hasMany(ProCertificateBatchRun::class, 'batch_id'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
