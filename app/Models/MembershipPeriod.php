<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MembershipPeriod extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'valid_from' => 'immutable_date', 'valid_until' => 'immutable_date',
            'created_at' => 'immutable_datetime', 'payload' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Approved membership periods are immutable.'));
        static::deleting(fn () => throw new LogicException('Approved membership periods cannot be deleted.'));
    }

    public function membership(): BelongsTo { return $this->belongsTo(Membership::class); }
    public function audit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'audit_log_id'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
