<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Minimal non-content identity tombstone: no holder, title, barcode, or document bytes. */
final class LegacyCertificateExclusion extends Model
{
    public $timestamps = false;
    protected $table = 'legacy_certificate_exclusions';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['purge_id' => 'integer', 'local_id' => 'integer', 'source_id' => 'string'];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (PHP_SAPI !== 'cli' || ! app()->runningInConsole()) {
                throw new LogicException('LEGACY_PURGE_CLI_ONLY');
            }
        });
        static::updating(fn () => throw new LogicException('LEGACY_PURGE_EXCLUSION_IMMUTABLE'));
        static::deleting(fn () => throw new LogicException('LEGACY_PURGE_EXCLUSION_IMMUTABLE'));
    }
}
