<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ProCertificateDeliveryContact extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['email'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Certificate delivery contacts cannot be deleted.'));
        static::updating(function (self $contact): void {
            if ($contact->isDirty('certificate_id')) {
                throw new LogicException('Certificate delivery contact ownership is immutable.');
            }
        });
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(ProCertificate::class, 'certificate_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function integrityAudit(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'integrity_audit_id');
    }
}
