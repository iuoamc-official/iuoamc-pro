<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MembershipApplication extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['date_of_birth', 'address', 'identification_number', 'qualifications', 'photo_path'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'encrypted',
            'address' => 'encrypted',
            'identification_number' => 'encrypted',
            'qualifications' => 'encrypted',
            'consent_at' => 'immutable_datetime',
            'lock_version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Membership applications cannot be deleted.'));
        static::updating(function (self $application): void {
            if ($application->isDirty('membership_id')) {
                throw new LogicException('Membership application ownership is immutable.');
            }
        });
    }

    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
