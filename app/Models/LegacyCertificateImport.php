<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class LegacyCertificateImport extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['manifest_hmac', 'provenance'];

    protected function casts(): array
    {
        return ['record_count' => 'integer', 'audit_id' => 'integer', 'summary' => 'array',
            'provenance' => 'encrypted:array'];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (PHP_SAPI !== 'cli' || ! app()->runningInConsole()) {
                throw new LogicException('LEGACY_CERTIFICATE_CLI_IMPORT_ONLY');
            }
        });
        static::updating(fn () => throw new LogicException('LEGACY_CERTIFICATE_IMPORT_IMMUTABLE'));
        static::deleting(fn () => throw new LogicException('LEGACY_CERTIFICATE_IMPORT_IMMUTABLE'));
    }

    public function audit(): BelongsTo { return $this->belongsTo(AuditLog::class, 'audit_id'); }
}
