<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class AccountDocument extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = ['recipient_email', 'email_hmac', 'pdf_path'];

    protected function casts(): array
    {
        return [
            'recipient_email' => 'encrypted',
            'amount' => 'decimal:2',
            'issued_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Issued account documents are immutable.'));
        static::deleting(fn () => throw new LogicException('Issued account documents cannot be deleted.'));
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function issuer(): BelongsTo { return $this->belongsTo(User::class, 'issued_by'); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
}
