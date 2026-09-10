<?php

namespace App\Services;

use App\Models\AuditLog;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

final class IntegrityService
{
    public const VERSION = 'ed25519-sha256-v1';

    private string $privateKeyPath;

    private string $publicKeyPath;

    public function __construct()
    {
        $this->privateKeyPath = storage_path('app/secure/integrity/ed25519.secret');
        $this->publicKeyPath = storage_path('app/secure/integrity/ed25519.public');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, int|string|null>
     */
    public function seal(array $payload, int $sequence, ?string $previousHash): array
    {
        $privateKey = $this->readKey($this->privateKeyPath, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES);
        $publicKey = $this->readKey($this->publicKeyPath, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        $payloadHash = $this->payloadHash($payload);
        $recordHash = $this->recordHash($sequence, $previousHash, $payloadHash);

        return [
            'sequence_number' => $sequence,
            'previous_hash' => $previousHash,
            'payload_hash' => $payloadHash,
            'record_hash' => $recordHash,
            'signature' => base64_encode(
                sodium_crypto_sign_detached(hex2bin($recordHash), $privateKey)
            ),
            'signing_key_id' => hash('sha256', $publicKey),
            'integrity_version' => self::VERSION,
        ];
    }

    /** @return array<string, mixed> */
    public function payloadFor(AuditLog $log): array
    {
        return [
            'actor_id' => $log->actor_id === null ? null : (int) $log->actor_id,
            'event' => (string) $log->event,
            'auditable_type' => $log->auditable_type,
            'auditable_id' => $log->auditable_id === null ? null : (string) $log->auditable_id,
            'ip_address' => $log->ip_address,
            'user_agent' => $log->user_agent,
            'old_values' => $log->old_values,
            'new_values' => $log->new_values,
            'metadata' => $log->metadata,
            'occurred_at' => $log->occurred_at,
        ];
    }

    /** @return array{valid: bool, reason: string, key_id: string|null} */
    public function verifyAuditLog(AuditLog $log): array
    {
        if (
            ! $log->sequence_number ||
            ! $log->payload_hash ||
            ! $log->record_hash ||
            ! $log->signature ||
            ! $log->signing_key_id ||
            $log->integrity_version !== self::VERSION
        ) {
            return ['valid' => false, 'reason' => 'missing_integrity_data', 'key_id' => null];
        }

        try {
            $publicKey = $this->readKey($this->publicKeyPath, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
            $payloadHash = $this->payloadHash($this->payloadFor($log));
            $recordHash = $this->recordHash(
                (int) $log->sequence_number,
                $log->previous_hash,
                $payloadHash
            );
            $signature = base64_decode((string) $log->signature, true);

            if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
                return ['valid' => false, 'reason' => 'invalid_signature_encoding', 'key_id' => null];
            }

            $valid = hash_equals((string) $log->payload_hash, $payloadHash)
                && hash_equals((string) $log->record_hash, $recordHash)
                && hash_equals((string) $log->signing_key_id, hash('sha256', $publicKey))
                && sodium_crypto_sign_verify_detached(
                    $signature,
                    hex2bin($recordHash),
                    $publicKey
                );

            return [
                'valid' => $valid,
                'reason' => $valid ? 'verified' : 'integrity_mismatch',
                'key_id' => hash('sha256', $publicKey),
            ];
        } catch (RuntimeException) {
            return ['valid' => false, 'reason' => 'key_unavailable', 'key_id' => null];
        }
    }

    /** @return array{valid: bool, count: int, latest_hash: string|null, errors: array<int, array<string, int|string>>} */
    public function verifyChain(): array
    {
        $expectedSequence = 1;
        $previousHash = null;
        $errors = [];
        $count = 0;

        AuditLog::query()
            ->orderBy('sequence_number')
            ->orderBy('id')
            ->each(function (AuditLog $log) use (&$expectedSequence, &$previousHash, &$errors, &$count): void {
                $count++;
                $verification = $this->verifyAuditLog($log);

                if ((int) $log->sequence_number !== $expectedSequence) {
                    $errors[] = ['id' => (int) $log->id, 'reason' => 'sequence_gap'];
                }

                if (($log->previous_hash ?: null) !== $previousHash) {
                    $errors[] = ['id' => (int) $log->id, 'reason' => 'chain_mismatch'];
                }

                if (! $verification['valid']) {
                    $errors[] = ['id' => (int) $log->id, 'reason' => $verification['reason']];
                }

                $previousHash = $log->record_hash;
                $expectedSequence++;
            });

        return [
            'valid' => $errors === [],
            'count' => $count,
            'latest_hash' => $previousHash,
            'errors' => array_slice($errors, 0, 100),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    private function recordHash(int $sequence, ?string $previousHash, string $payloadHash): string
    {
        return hash('sha256', implode('|', [
            'IUOAMC-AUDIT-V1',
            (string) $sequence,
            $previousHash ?: str_repeat('0', 64),
            $payloadHash,
        ]));
    }

    private function readKey(string $path, int $expectedBytes): string
    {
        $encoded = is_file($path) ? trim((string) file_get_contents($path)) : '';
        $key = base64_decode($encoded, true);

        if ($key === false || strlen($key) !== $expectedBytes) {
            throw new RuntimeException('Cryptographic integrity key is unavailable or invalid.');
        }

        return $key;
    }

    private function canonicalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            $utc = (clone $value)->setTimezone(new DateTimeZone('UTC'));

            return $utc->format('Y-m-d\\TH:i:sP');
        }

        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
