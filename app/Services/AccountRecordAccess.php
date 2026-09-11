<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AccountDocument;
use App\Models\AccountRecordLink;
use App\Models\Membership;
use App\Models\ProCertificate;
use App\Models\ProCertificateDeliveryContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class AccountRecordAccess
{
    public const MEMBERSHIP = 'membership';
    public const PRO_CERTIFICATE = 'pro_certificate';
    public const DOCUMENT = 'account_document';

    public static function normalizeEmail(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    public static function emailHmac(string $email): string
    {
        $key = (string) config('app.key');
        if ($key === '') {
            throw new RuntimeException('ACCOUNT_LINK_KEY_UNAVAILABLE');
        }

        return hash_hmac('sha256', self::normalizeEmail($email), $key);
    }

    public function sync(User $user, bool $force = false): void
    {
        abort_unless($user->isActive() && $user->hasVerifiedEmail(), 403);
        $email = self::normalizeEmail($user->email);
        $hmac = self::emailHmac($email);
        $cacheKey = 'account-record-sync:'.$user->id.':'.$hmac;
        if (! $force && Cache::has($cacheKey)) { return; }
        $matches = [self::MEMBERSHIP => [], self::PRO_CERTIFICATE => [], self::DOCUMENT => []];

        Membership::query()->select(['id', 'email'])->orderBy('id')->chunkById(200, function ($records) use (&$matches, $email): void {
            foreach ($records as $record) {
                try {
                    if (hash_equals($email, self::normalizeEmail($record->email))) {
                        $matches[self::MEMBERSHIP][] = (int) $record->id;
                    }
                } catch (Throwable) {
                    // A corrupt encrypted contact is never linked by approximation.
                }
            }
        });

        ProCertificate::query()->whereIn('status', ['issued', 'revoked'])->orderBy('id')
            ->chunkById(100, function ($records) use (&$matches, $email): void {
                foreach ($records as $record) {
                    try {
                        if (hash_equals($email, self::normalizeEmail($this->certificateEmail($record)))) {
                            $matches[self::PRO_CERTIFICATE][] = (int) $record->id;
                        }
                    } catch (Throwable) {
                        // Fail closed when a delivery contact cannot be verified or decrypted.
                    }
                }
            });

        $matches[self::DOCUMENT] = AccountDocument::query()->where('email_hmac', $hmac)
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        DB::transaction(function () use ($user, $hmac, $matches): void {
            foreach ($matches as $type => $ids) {
                $scope = AccountRecordLink::query()->where('user_id', $user->id)->where('record_type', $type);
                $ids === [] ? $scope->delete() : $scope->whereNotIn('record_id', $ids)->delete();
                foreach ($ids as $id) {
                    AccountRecordLink::query()->updateOrCreate(
                        ['user_id' => $user->id, 'record_type' => $type, 'record_id' => $id],
                        ['email_hmac' => $hmac, 'matched_at' => now()->utc()->startOfSecond()]
                    );
                }
            }
        }, 3);
        Cache::put($cacheKey, true, now()->addMinutes(5));
    }

    public function claimMembership(User $user, Membership $membership): void
    {
        abort_unless($user->isActive() && $user->hasVerifiedEmail(), 403);
        $email = self::normalizeEmail($user->email);
        abort_unless($email !== '' && hash_equals($email, self::normalizeEmail($membership->email)), 403);
        AccountRecordLink::query()->updateOrCreate(
            ['user_id' => $user->id, 'record_type' => self::MEMBERSHIP, 'record_id' => $membership->id],
            ['email_hmac' => self::emailHmac($email), 'matched_at' => now()->utc()->startOfSecond()]
        );
    }

    public function ids(User $user, string $type): array
    {
        return AccountRecordLink::query()->where('user_id', $user->id)->where('record_type', $type)
            ->pluck('record_id')->map(static fn ($id): int => (int) $id)->all();
    }

    public function owns(User $user, string $type, int $id): bool
    {
        abort_unless($user->isActive() && $user->hasVerifiedEmail(), 403);
        $link = AccountRecordLink::query()->where('user_id', $user->id)
            ->where('record_type', $type)->where('record_id', $id)->first();
        if ($link === null) { return false; }

        $email = self::normalizeEmail($user->email);
        try {
            $matches = match ($type) {
                self::MEMBERSHIP => hash_equals($email, self::normalizeEmail(Membership::query()->find($id)?->email)),
                self::PRO_CERTIFICATE => hash_equals($email, self::normalizeEmail(
                    ($certificate = ProCertificate::query()->whereIn('status', ['issued', 'revoked'])->find($id))
                        ? $this->certificateEmail($certificate) : null
                )),
                self::DOCUMENT => AccountDocument::query()->whereKey($id)
                    ->where('email_hmac', self::emailHmac($email))->exists(),
                default => false,
            };
        } catch (Throwable) {
            $matches = false;
        }
        if (! $matches) { $link->delete(); }

        return $matches;
    }

    private function certificateEmail(ProCertificate $certificate): ?string
    {
        $contact = ProCertificateDeliveryContact::query()->where('certificate_id', $certificate->id)
            ->with('integrityAudit')->first();
        if ($contact === null) { return $certificate->recipient_email; }

        return app(ProCertificateCorrection::class)->verifyContact($contact) ? $contact->email : null;
    }
}
