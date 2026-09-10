<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuditTrail
{
    public static function record(
        string $event,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        array $metadata = [],
        ?int $actorId = null
    ): AuditLog {
        $request = request();

        return DB::transaction(function () use (
            $event,
            $subject,
            $oldValues,
            $newValues,
            $metadata,
            $actorId,
            $request
        ): AuditLog {
            $last = AuditLog::query()
                ->whereNotNull('sequence_number')
                ->orderByDesc('sequence_number')
                ->lockForUpdate()
                ->first();

            $occurredAt = now()->utc()->startOfSecond();
            $data = [
                'actor_id' => $actorId ?? auth()->id(),
                'event' => $event,
                'auditable_type' => $subject?->getMorphClass(),
                'auditable_id' => $subject?->getKey(),
                'ip_address' => $request?->ip(),
                'user_agent' => Str::limit((string) $request?->userAgent(), 1000),
                'old_values' => self::scrub($oldValues) ?: null,
                'new_values' => self::scrub($newValues) ?: null,
                'metadata' => self::scrub($metadata) ?: null,
                'occurred_at' => $occurredAt,
            ];

            $prototype = new AuditLog($data);
            $seal = app(IntegrityService::class)->seal(
                app(IntegrityService::class)->payloadFor($prototype),
                ((int) ($last?->sequence_number ?? 0)) + 1,
                $last?->record_hash
            );

            return AuditLog::create($data + $seal);
        }, 5);
    }

    /** @param array<string|int, mixed> $values */
    private static function scrub(array $values): array
    {
        $blocked = [
            'password',
            'password_confirmation',
            'current_password',
            'remember_token',
            'token',
            'secret',
            'private_key',
        ];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(Str::lower($key), $blocked, true)) {
                unset($values[$key]);
                continue;
            }

            if (is_array($value)) {
                $values[$key] = self::scrub($value);
            }
        }

        return $values;
    }
}
