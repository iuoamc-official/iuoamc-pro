<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class CheckGmailApiMail extends Command
{
    protected $signature = 'iuoamc:gmail-api-readiness
        {--send-to= : Optional recipient for a real delivery test}';

    protected $description = 'Check the secure Gmail API mail transport without exposing credentials';

    public function handle(): int
    {
        $mailer = (string) config('mail.default');
        $from = strtolower(trim((string) config('mail.from.address')));
        $sender = strtolower(trim((string) config('mail.mailers.gmail-api.sender')));
        $checks = [
            'DEFAULT_MAILER' => $mailer === 'gmail-api',
            'CLIENT_ID' => trim((string) config('mail.mailers.gmail-api.client_id')) !== '',
            'CLIENT_SECRET' => trim((string) config('mail.mailers.gmail-api.client_secret')) !== '',
            'REFRESH_TOKEN' => trim((string) config('mail.mailers.gmail-api.refresh_token')) !== '',
            'SENDER' => filter_var($sender, FILTER_VALIDATE_EMAIL) !== false,
            'FROM_MATCHES_SENDER' => $from !== '' && hash_equals($sender, $from),
        ];

        foreach ($checks as $name => $ready) {
            $this->line($name.'='.($ready ? 'READY' : 'BLOCKED'));
        }

        if (in_array(false, $checks, true)) {
            $this->error('GMAIL_API_MAIL_NOT_READY');

            return self::FAILURE;
        }

        $recipient = trim((string) $this->option('send-to'));

        if ($recipient === '') {
            $this->components->info('Gmail API mail configuration is ready');

            return self::SUCCESS;
        }

        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('TEST_RECIPIENT_INVALID');

            return self::INVALID;
        }

        try {
            Mail::mailer('gmail-api')->raw(
                'IUOAMC secure account portal mail test. UTC: '.now()->utc()->toIso8601String(),
                fn ($message) => $message
                    ->to($recipient)
                    ->subject('IUOAMC secure mail test'),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->error('GMAIL_API_TEST_SEND_FAILED');

            return self::FAILURE;
        }

        $this->components->info('Gmail API test message accepted');
        $this->line('TEST_RECIPIENT='.$recipient);

        return self::SUCCESS;
    }
}
