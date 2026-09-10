<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class ProCertificateStudent extends Model
{
    protected $table = 'pro_certificate_students';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $hidden = [
        'private_name',
        'name_fingerprint',
    ];

    protected function casts(): array
    {
        return [
            'private_name' => 'encrypted',
            'source_record_count' => 'integer',
            'first_source_record_id' => 'integer',
            'last_source_record_id' => 'integer',
            'archived_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static fn () => throw new LogicException(
            'Preserved student identities are immutable.'
        ));
        static::deleting(static fn () => throw new LogicException(
            'Preserved student identities cannot be deleted.'
        ));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
