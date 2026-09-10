<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Organization;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class EntityTaxRegistry
{
    public const KEY = 'tax_registry_encrypted';

    public function canView(): bool
    {
        $user = auth()->user();
        return $user !== null && $user->status === 'active' && $user->hasRole('super-admin');
    }

    /** Decryption for display is only available to an active super-administrator. */
    public function forDisplay(Organization $organization): array
    {
        if (! $this->canView()) {
            abort(403);
        }
        $metadata = $organization->metadata ?? [];
        if (! array_key_exists(self::KEY, $metadata)) {
            return [];
        }
        return self::decode((string) $metadata[self::KEY]);
    }

    /** Server-side storage utility; never accepts unvalidated HTTP input. */
    public static function decode(string $ciphertext): array
    {
        $data = json_decode(Crypt::decryptString($ciphertext), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['schema'] ?? null) !== 1 || ! is_array($data['identifiers'] ?? null)) {
            throw new RuntimeException('INVALID_TAX_REGISTRY_DATA');
        }
        foreach ($data['identifiers'] as $item) {
            self::validateIdentifier($item);
        }
        return $data['identifiers'];
    }

    public static function validateIdentifier(array $item): void
    {
        $pattern = match ($item['type'] ?? '') {
            'UTR' => '/^[0-9]{10}$/D',
            'EIN' => '/^[0-9]{2}-[0-9]{7}$/D',
            'CRA_BN' => '/^[0-9]{9}$/D',
            'CRA_CT' => '/^[0-9]{9}RC[0-9]{4}$/D',
            default => throw new RuntimeException('UNSUPPORTED_TAX_IDENTIFIER_TYPE'),
        };
        if (! is_string($item['value'] ?? null) || ! preg_match($pattern, $item['value'])) {
            throw new RuntimeException('INVALID_TAX_IDENTIFIER_FORMAT');
        }
    }

    /** Add identifiers, preserve existing provenance, and reject conflicting values. */
    public static function enrich(array $metadata, array $incoming): array
    {
        if ($incoming === []) {
            return $metadata;
        }
        $identifiers = array_key_exists(self::KEY, $metadata)
            ? self::decode((string) $metadata[self::KEY]) : [];
        $byType = [];
        foreach ($identifiers as $item) {
            if (isset($byType[$item['type']])) {
                throw new RuntimeException('DUPLICATE_EXISTING_TAX_IDENTIFIER_TYPE');
            }
            $byType[$item['type']] = $item;
        }
        $changed = false;
        foreach ($incoming as $item) {
            self::validateIdentifier($item);
            if (isset($byType[$item['type']])) {
                if (! hash_equals($byType[$item['type']]['value'], $item['value'])) {
                    throw new RuntimeException('EXISTING_TAX_IDENTIFIER_CONFLICT');
                }
            } else {
                $byType[$item['type']] = $item;
                $changed = true;
            }
        }
        if ($changed) {
            $metadata[self::KEY] = Crypt::encryptString(json_encode([
                'schema' => 1, 'identifiers' => array_values($byType),
            ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }
        return $metadata;
    }
}
