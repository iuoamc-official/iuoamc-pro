<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class LegacyCertificate extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $hidden = ['original_fields', 'projection_hmac', 'sql_candidate_source_ids'];

    protected function casts(): array
    {
        return ['source_id' => 'string', 'original_fields' => 'encrypted:array',
            'sql_candidate_source_ids' => 'array', 'candidate_count' => 'integer',
            'is_ambiguous' => 'boolean', 'import_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (PHP_SAPI !== 'cli' || ! app()->runningInConsole()) {
                throw new LogicException('LEGACY_CERTIFICATE_CLI_IMPORT_ONLY');
            }
        });
        static::updating(fn () => throw new LogicException('LEGACY_CERTIFICATE_IMMUTABLE'));
        static::deleting(fn () => throw new LogicException('LEGACY_CERTIFICATE_IMMUTABLE'));
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(LegacyCertificateImport::class, 'import_id');
    }
}
