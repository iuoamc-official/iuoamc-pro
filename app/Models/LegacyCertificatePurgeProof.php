<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

final class LegacyCertificatePurgeProof extends Model
{
    public $timestamps = false;
    protected $table = 'legacy_certificate_purge_proofs';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'import_id' => 'integer', 'audit_id' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (): void {
            if (PHP_SAPI !== 'cli' || ! app()->runningInConsole()) {
                throw new LogicException('LEGACY_PURGE_CLI_ONLY');
            }
        });
        static::updating(fn () => throw new LogicException('LEGACY_PURGE_PROOF_IMMUTABLE'));
        static::deleting(fn () => throw new LogicException('LEGACY_PURGE_PROOF_IMMUTABLE'));
    }
}
