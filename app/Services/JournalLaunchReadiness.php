<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalEditorialMember;
use Carbon\CarbonImmutable;

final class JournalLaunchReadiness
{
    public const FREQUENCIES = ['continuous', 'quarterly', 'biannual', 'annual'];

    public const FEE_POLICIES = ['no_fees', 'fees_apply', 'case_by_case'];

    /** @return array<string, array{passed: bool, detail: string}> */
    public function checks(Journal $journal): array
    {
        $settings = $journal->settings ?? [];
        $contactEmail = trim((string) ($settings['contact_email'] ?? ''));
        $backupReference = trim((string) ($settings['backup_reference'] ?? ''));
        $backupVerifiedAt = $this->date($settings['backup_verified_at'] ?? null);
        $activeBoard = $journal->editorialMembers()->where('status', 'active')->whereNotNull('consented_at');
        $mail = $this->mailConfiguration();
        $storageRoot = (string) config('filesystems.disks.local.root');

        return [
            'contact' => [
                'passed' => $this->isRealEmail($contactEmail),
                'detail' => $contactEmail ?: trans('journal.readiness.not_configured'),
            ],
            'frequency' => [
                'passed' => in_array($settings['publication_frequency'] ?? null, self::FREQUENCIES, true),
                'detail' => trans('journal.frequencies.'.($settings['publication_frequency'] ?? 'unconfigured')),
            ],
            'fees' => [
                'passed' => in_array($settings['fee_policy'] ?? null, self::FEE_POLICIES, true),
                'detail' => trans('journal.fee_policies.'.($settings['fee_policy'] ?? 'unconfigured')),
            ],
            'editor_in_chief' => [
                'passed' => (clone $activeBoard)->where('role', 'editor_in_chief')->exists(),
                'detail' => trans('journal.readiness.editor_in_chief_detail'),
            ],
            'editorial_board' => [
                'passed' => (clone $activeBoard)->count() >= 3,
                'detail' => trans('journal.readiness.editorial_board_detail', ['count' => (clone $activeBoard)->count()]),
            ],
            'first_issue' => [
                'passed' => $journal->issues()->where('status', 'published')->whereNotNull('published_at')->exists(),
                'detail' => trans('journal.readiness.first_issue_detail'),
            ],
            'first_article' => [
                'passed' => $journal->articles()->published()->exists(),
                'detail' => trans('journal.readiness.first_article_detail'),
            ],
            'mail' => [
                'passed' => $mail['ready'],
                'detail' => $mail['detail'],
            ],
            'outbox' => [
                'passed' => ! $journal->notificationOutbox()->where('status', 'failed')->exists()
                    && ! $journal->notificationOutbox()->where('status', 'pending')->where('available_at', '<', now()->subMinutes(30))->exists()
                    && ! $journal->notificationOutbox()->where('status', 'sending')->where('updated_at', '<', now()->subMinutes(15))->exists(),
                'detail' => trans('journal.readiness.outbox_detail'),
            ],
            'private_storage' => [
                'passed' => $storageRoot !== '' && is_dir($storageRoot) && is_writable($storageRoot),
                'detail' => trans('journal.readiness.private_storage_detail'),
            ],
            'app_key' => [
                'passed' => trim((string) config('app.key')) !== '',
                'detail' => trans('journal.readiness.app_key_detail'),
            ],
            'backup' => [
                'passed' => $backupReference !== '' && $backupVerifiedAt?->greaterThanOrEqualTo(now()->subDays(7)) === true,
                'detail' => $backupVerifiedAt === null
                    ? trans('journal.readiness.backup_missing')
                    : trans('journal.readiness.backup_detail', ['date' => $backupVerifiedAt->toDateTimeString()]),
            ],
        ];
    }

    public function isReady(Journal $journal): bool
    {
        return collect($this->checks($journal))->every(fn (array $check): bool => $check['passed']);
    }

    private function isRealEmail(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        $domain = mb_strtolower((string) str($email)->afterLast('@'));

        return ! in_array($domain, ['example.com', 'example.org', 'example.net', 'example.test', 'localhost'], true)
            && ! str_ends_with($domain, '.test');
    }

    /** @return array{ready: bool, detail: string} */
    private function mailConfiguration(): array
    {
        $default = (string) config('mail.default');
        $mailer = config('mail.mailers.'.$default, []);
        $transport = (string) ($mailer['transport'] ?? '');
        $from = trim((string) config('mail.from.address'));
        $blocked = in_array($transport, ['log', 'array', ''], true);

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            foreach (($mailer['mailers'] ?? []) as $child) {
                $childTransport = (string) config('mail.mailers.'.$child.'.transport');
                $blocked = $blocked || in_array($childTransport, ['log', 'array', ''], true);
            }
        }

        return [
            'ready' => ! $blocked && $this->isRealEmail($from),
            'detail' => trans('journal.readiness.mail_detail', ['mailer' => $default, 'from' => $from ?: '—']),
        ];
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
