<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ProCertificateSigner
{
    /** Signed bytes: this ASCII domain, a NUL, then the raw SHA-256 digest. */
    public const SIGNATURE_DOMAIN = "IUOAMC-PRO-CERTIFICATE-SIGNATURE-V1\0";

    private const MAX_DEPTH = 128;

    /** Installer-only, idempotent initialization. Never replace an existing key pair. */
    public function initialize(): void
    {
        $this->requireSodium();
        $parent = storage_path('app/secure');
        $this->ensureDirectory($parent);
        $lockPath = $parent.'/.pro-certificates.initialize.lock';
        $lock = $this->openLock($lockPath);
        $staging = null;
        $secret = null;
        $pair = null;

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock certificate signing key initialization.');
            }

            $directory = $this->directory();
            $this->assertNoSymlinks($directory);
            if (file_exists($directory)) {
                $this->assertPrivateDirectory($directory);
                $entries = scandir($directory);
                if ($entries === false) {
                    throw new RuntimeException('Cannot inspect certificate signing key directory.');
                }
                if (array_diff($entries, ['.', '..']) !== []) {
                    [$secret] = $this->readPair();

                    return;
                }
            }

            // Publish a complete directory with one atomic rename. An interrupted
            // initialization cannot publish only one member of a new key pair.
            $staging = $parent.'/.pro-certificates.keys.'.bin2hex(random_bytes(16));
            if (! mkdir($staging, 0700) || ! chmod($staging, 0700)) {
                throw new RuntimeException('Cannot create certificate signing key staging directory.');
            }
            $pair = sodium_crypto_sign_keypair();
            $secret = sodium_crypto_sign_secretkey($pair);
            $public = sodium_crypto_sign_publickey($pair);
            $this->writeNewKey($staging.'/ed25519.secret', $secret, 0600);
            $this->writeNewKey($staging.'/ed25519.public', $public, 0644);

            // rename cannot replace a nonempty directory. Recheck links as well;
            // all cooperating initializers serialize on the same private lock.
            $this->assertNoSymlinks($directory);
            if (! rename($staging, $directory)) {
                throw new RuntimeException('Cannot publish certificate signing keys without replacing existing files.');
            }
            $staging = null;
            [$verifiedSecret] = $this->readPair();
            sodium_memzero($verifiedSecret);
        } finally {
            if (is_string($secret)) {
                sodium_memzero($secret);
            }
            if (is_string($pair)) {
                sodium_memzero($pair);
            }
            if ($staging !== null) {
                // These are exclusively created files in our random directory.
                @unlink($staging.'/ed25519.secret');
                @unlink($staging.'/ed25519.public');
                @rmdir($staging);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** @return array{payload_sha256: string, signature: string, signing_key_id: string} */
    public function sign(array $payload): array
    {
        [$secret, $public] = $this->readPair();
        try {
            $digest = self::digest($payload);

            return [
                'payload_sha256' => $digest,
                'signature' => base64_encode(sodium_crypto_sign_detached(
                    self::SIGNATURE_DOMAIN.hex2bin($digest),
                    $secret
                )),
                'signing_key_id' => self::keyId($public),
            ];
        } finally {
            sodium_memzero($secret);
        }
    }

    public function verify(array $payload, array $seal): bool
    {
        $secret = null;
        try {
            foreach (['payload_sha256', 'signature', 'signing_key_id'] as $field) {
                if (! isset($seal[$field]) || ! is_string($seal[$field])) {
                    return false;
                }
            }
            if (! preg_match('/\A[a-f0-9]{64}\z/D', $seal['payload_sha256'])) {
                return false;
            }
            $this->requireSodium();
            $signature = self::decodeBase64($seal['signature'], SODIUM_CRYPTO_SIGN_BYTES);
            [$secret, $public] = $this->readPair();
            $digest = self::digest($payload);

            return hash_equals($digest, $seal['payload_sha256'])
                && hash_equals(self::keyId($public), $seal['signing_key_id'])
                && sodium_crypto_sign_verify_detached(
                    $signature,
                    self::SIGNATURE_DOMAIN.hex2bin($digest),
                    $public
                );
        } catch (Throwable) {
            return false;
        } finally {
            if (is_string($secret)) {
                sodium_memzero($secret);
            }
        }
    }

    /** Canonical base64 encoding of the dedicated, 32-byte Ed25519 public key. */
    public function publicKey(): string
    {
        [$secret, $public] = $this->readPair();
        sodium_memzero($secret);

        return base64_encode($public);
    }

    public static function digest(array $payload): string
    {
        return hash('sha256', self::canonicalJson($payload));
    }

    /**
     * Module-specific canonical JSON, not a claim of RFC 8785 compliance.
     * Lists retain order; map keys sort bytewise as strings. Only arrays and
     * JSON scalars are accepted. Float encoding uses PHP's shortest round-trip
     * representation, retaining .0; malformed UTF-8 is rejected, not repaired.
     */
    public static function canonicalJson(array $payload): string
    {
        $normalized = self::normalize($payload, 0);
        $precision = ini_get('serialize_precision');
        if ($precision !== '-1' && ini_set('serialize_precision', '-1') === false) {
            throw new RuntimeException('Cannot configure deterministic certificate JSON serialization.');
        }

        try {
            return json_encode(
                $normalized,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
                self::MAX_DEPTH + 2
            );
        } finally {
            if ($precision !== false && $precision !== '-1') {
                ini_set('serialize_precision', $precision);
            }
        }
    }

    private static function normalize(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidArgumentException('Certificate payload exceeds the supported nesting depth.');
        }
        if (is_array($value)) {
            $list = array_is_list($value);
            if (! $list) {
                ksort($value, SORT_STRING);
            }
            foreach ($value as $key => $child) {
                $value[$key] = self::normalize($child, $depth + 1);
            }

            // Preserve map semantics even when sorting makes integer keys consecutive.
            return $list ? $value : (object) $value;
        }
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }
        if (is_float($value) && is_finite($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Certificate payload contains a non-JSON value.');
    }

    /** @return array{0: string, 1: string} */
    private function readPair(): array
    {
        $this->requireSodium();
        $directory = $this->directory();
        $this->assertNoSymlinks($directory);
        $this->assertPrivateDirectory($directory);
        $secret = $this->readKey($directory.'/ed25519.secret', SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, [0600]);
        $derivedPair = null;
        $derivedSecret = null;

        try {
            $public = $this->readKey($directory.'/ed25519.public', SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, [0600, 0644]);
            // Derive from the seed: extracting the public half of a 64-byte
            // secret alone does not detect a corrupted secret seed.
            $derivedPair = sodium_crypto_sign_seed_keypair(substr($secret, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES));
            $derivedSecret = sodium_crypto_sign_secretkey($derivedPair);
            if (! hash_equals($derivedSecret, $secret)
                || ! hash_equals(sodium_crypto_sign_publickey($derivedPair), $public)) {
                throw new RuntimeException('Certificate signing key pair does not match.');
            }

            return [$secret, $public];
        } catch (Throwable $error) {
            sodium_memzero($secret);
            throw $error;
        } finally {
            if (is_string($derivedPair)) {
                sodium_memzero($derivedPair);
            }
            if (is_string($derivedSecret)) {
                sodium_memzero($derivedSecret);
            }
        }
    }

    private function directory(): string
    {
        return storage_path('app/secure/pro-certificates');
    }

    private function requireSodium(): void
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('The sodium extension is required for certificate signing.');
        }
    }

    private static function keyId(string $public): string
    {
        return 'ed25519-sha256:'.hash('sha256', $public);
    }

    private static function decodeBase64(string $encoded, int $length): string
    {
        $decoded = base64_decode($encoded, true);
        if ($decoded === false || strlen($decoded) !== $length || base64_encode($decoded) !== $encoded) {
            throw new RuntimeException('Invalid certificate cryptographic encoding.');
        }

        return $decoded;
    }

    private function assertNoSymlinks(string $path): void
    {
        // The target's open_basedir permits the application root, but not its
        // ancestors. Treat the resolved application root as the trusted anchor;
        // never probe /home (or another external ancestor) while checking keys.
        $root = realpath(base_path());
        if ($root === false || ! str_starts_with($path, $root.'/')) {
            throw new RuntimeException('Certificate signing key path must be inside the application root.');
        }
        $current = $root;
        foreach (explode('/', substr($path, strlen($root) + 1)) as $component) {
            if ($component === '') {
                continue;
            }
            if ($component === '.' || $component === '..') {
                throw new RuntimeException('Certificate signing key path must not contain relative components.');
            }
            $current .= '/'.$component;
            clearstatcache(true, $current);
            if (is_link($current)) {
                throw new RuntimeException('Symlinks are not permitted in certificate signing key paths.');
            }
        }
    }

    private function ensureDirectory(string $path): void
    {
        $this->assertNoSymlinks($path);
        if (! is_dir($path)) {
            $parent = dirname($path);
            if (! is_dir($parent)) {
                throw new RuntimeException('Certificate signing key storage parent is unavailable.');
            }
            if (! @mkdir($path, 0700) && ! is_dir($path)) {
                throw new RuntimeException('Cannot create certificate signing key storage.');
            }
        }
        $this->assertNoSymlinks($path);
        // The shared secure parent may also hold pre-existing audit keys. Do not
        // alter its permissions or contents; the dedicated key directory is 0700.
        $mode = fileperms($path);
        if ($mode === false || ($mode & 0022) !== 0) {
            throw new RuntimeException('Certificate signing key storage must not be group- or world-writable.');
        }
    }

    private function assertPrivateDirectory(string $path): void
    {
        clearstatcache(true, $path);
        $stat = lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0040000 || ($stat['mode'] & 0777) !== 0700) {
            throw new RuntimeException('Certificate signing keys are unavailable or their directory is not private.');
        }
    }

    /** @return resource */
    private function openLock(string $path)
    {
        $this->assertNoSymlinks($path);
        $lock = @fopen($path, 'x+b');
        if ($lock !== false) {
            if (! chmod($path, 0600)) {
                fclose($lock);
                throw new RuntimeException('Cannot secure certificate signing initialization lock.');
            }
        } else {
            $lock = @fopen($path, 'r+b');
        }
        if ($lock === false) {
            throw new RuntimeException('Cannot open certificate signing initialization lock.');
        }
        try {
            $this->assertRegularFile($path, $lock, [0600]);

            return $lock;
        } catch (Throwable $error) {
            fclose($lock);
            throw $error;
        }
    }

    /** @param resource $handle @param list<int> $modes */
    private function assertRegularFile(string $path, $handle, array $modes): array
    {
        clearstatcache(true, $path);
        $pathStat = lstat($path);
        $handleStat = fstat($handle);
        if ($pathStat === false || $handleStat === false
            || ($pathStat['mode'] & 0170000) !== 0100000
            || ($handleStat['mode'] & 0170000) !== 0100000
            || $pathStat['ino'] !== $handleStat['ino'] || $pathStat['dev'] !== $handleStat['dev']
            || $handleStat['nlink'] !== 1 || ! in_array($handleStat['mode'] & 0777, $modes, true)) {
            throw new RuntimeException('Certificate signing key file is missing, unsafe, or has invalid permissions.');
        }

        return $handleStat;
    }

    /** @param list<int> $modes */
    private function readKey(string $path, int $length, array $modes): string
    {
        $this->assertNoSymlinks($path);
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Certificate signing key pair is not initialized; run the installer.');
        }
        try {
            $stat = $this->assertRegularFile($path, $handle, $modes);
            if ($stat['size'] < 1 || $stat['size'] > 256) {
                throw new RuntimeException('Invalid certificate signing key file size.');
            }
            $encoded = stream_get_contents($handle, 257);
            if ($encoded === false) {
                throw new RuntimeException('Cannot read certificate signing key.');
            }
            $this->assertRegularFile($path, $handle, $modes);

            return self::decodeBase64(rtrim($encoded, "\r\n"), $length);
        } finally {
            fclose($handle);
        }
    }

    private function writeNewKey(string $path, string $raw, int $mode): void
    {
        $handle = @fopen($path, 'x+b');
        if ($handle === false) {
            throw new RuntimeException('Refusing to overwrite a certificate signing key file.');
        }
        try {
            if (! chmod($path, $mode)) {
                throw new RuntimeException('Cannot secure a certificate signing key file.');
            }
            $encoded = base64_encode($raw)."\n";
            $offset = 0;
            while ($offset < strlen($encoded)) {
                $written = fwrite($handle, substr($encoded, $offset));
                if ($written === false || $written === 0) {
                    throw new RuntimeException('Cannot write certificate signing key.');
                }
                $offset += $written;
            }
            if (! fflush($handle) || ! fsync($handle)) {
                throw new RuntimeException('Cannot flush certificate signing key.');
            }
            $this->assertRegularFile($path, $handle, [$mode]);
        } finally {
            fclose($handle);
        }
    }
}
