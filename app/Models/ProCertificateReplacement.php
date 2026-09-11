<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ProCertificateReplacement extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['reason'];

    protected function casts(): array
    {
        return ['reason' => 'encrypted', 'created_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Certificate replacement links are immutable.'));
        static::deleting(fn () => throw new LogicException('Certificate replacement links cannot be deleted.'));
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ProCertificate::class, 'source_certificate_id');
    }

    public function replacement(): BelongsTo
    {
        return $this->belongsTo(ProCertificate::class, 'replacement_certificate_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function integrityAudit(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'integrity_audit_id');
    }
}
