<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Membership extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['email', 'phone', 'private_notes', 'last_reason'];

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'phone' => 'encrypted', 'private_notes' => 'encrypted',
            'last_reason' => 'encrypted', 'lock_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Membership records cannot be deleted.'));
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function integrityAudit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'integrity_audit_id'); }
    public function periods(): HasMany { return $this->hasMany(MembershipPeriod::class)->orderByDesc('version'); }

    public function effectiveStatus(?string $date = null): string
    {
        if ($this->status !== 'active') { return (string) $this->status; }
        $date ??= now()->utc()->toDateString();
        foreach ($this->periods as $period) {
            if ($period->valid_from->toDateString() <= $date && $period->valid_until->toDateString() >= $date) {
                return 'active';
            }
        }
        return $this->periods->contains(fn ($period) => $period->valid_from->toDateString() > $date)
            ? 'scheduled' : 'expired';
    }
}
