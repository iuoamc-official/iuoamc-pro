<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Command;
use Tests\TestCase;

final class IssueProCertificateCommandTest extends TestCase
{
    public function test_it_refuses_issuance_without_the_record_specific_confirmation_phrase(): void
    {
        $this->artisan('iuoamc:issue-pro-certificate', [
            'certificate' => '104',
            '--actor' => 'issuer@example.test',
            '--expected-recipient' => 'Expected Recipient',
            '--expected-program' => 'TEST',
            '--confirm' => 'WRONG',
        ])
            ->expectsOutputToContain('CONFIRMATION_PHRASE_INVALID')
            ->assertExitCode(Command::INVALID);
    }
}
