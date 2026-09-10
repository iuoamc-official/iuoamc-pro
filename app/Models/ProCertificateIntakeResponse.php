<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ProCertificateIntakeResponse extends Model
{
    protected $table = 'pro_certificate_intake_responses';
    protected $guarded = ['id'];
    protected $hidden = ['response_payload', 'nonce_hash', 'reviewed_by', 'integrity_audit_id'];
    protected function casts(): array
    {
        return ['program_id' => 'integer', 'matched_intake_id' => 'integer', 'reviewed_by' => 'integer',
            'integrity_audit_id' => 'integer', 'response_payload' => 'encrypted:array',
            'reviewed_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Intake response history requires a controlled retention operation.'));
        static::updating(static function (self $record): void {
            foreach (['program_id', 'response_payload', 'nonce_hash', 'created_at'] as $field) {
                if ($record->isDirty($field)) { throw new LogicException('Submitted recipient data is immutable.'); }
            }
            if ($record->getRawOriginal('status') !== 'submitted' && $record->isDirty(['status', 'matched_intake_id', 'reviewed_by', 'reviewed_at'])) {
                throw new LogicException('A reviewed response cannot be reassigned.');
            }
        });
    }
    public function program(): BelongsTo { return $this->belongsTo(ProCertificateIntakeProgram::class, 'program_id'); }
    public function matchedIntake(): BelongsTo { return $this->belongsTo(ProCertificateIntake::class, 'matched_intake_id'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
}
