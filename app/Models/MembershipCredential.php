<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MembershipCredential extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['payload', 'card_pdf_path', 'certificate_pdf_path'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'issued_at' => 'immutable_datetime',
            'signed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Issued membership credentials are immutable.'));
        static::deleting(fn () => throw new LogicException('Issued membership credentials cannot be deleted.'));
    }

    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
    public function period(): BelongsTo { return $this->belongsTo(MembershipPeriod::class, 'membership_period_id'); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
