<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class ProCertificateIntakeProgram extends Model
{
    protected $table = 'pro_certificate_intake_programs';
    protected $guarded = ['id'];
    protected $hidden = ['created_by', 'integrity_audit_id'];
    protected function casts(): array
    {
        return ['organization_id' => 'integer', 'created_by' => 'integer', 'integrity_audit_id' => 'integer',
            'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
    protected static function booted(): void
    {
        static::deleting(static fn () => throw new LogicException('Intake program history cannot be deleted.'));
        static::updating(static function (self $program): void {
            foreach (['organization_id', 'code', 'program_name_ar', 'program_name_en', 'program_name_fr', 'created_by', 'created_at'] as $field) {
                if ($program->isDirty($field)) { throw new LogicException('Intake program identity is immutable.'); }
            }
        });
    }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function responses(): HasMany { return $this->hasMany(ProCertificateIntakeResponse::class, 'program_id'); }
}
