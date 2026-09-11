<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AccountRecordAccess;
use Tests\TestCase;

final class AccountRecordAccessTest extends TestCase
{
    public function test_email_matching_is_trimmed_case_insensitive_and_hmac_is_not_plaintext(): void
    {
        config()->set('app.key', 'base64:portal-test-key');
        self::assertSame('member@example.org', AccountRecordAccess::normalizeEmail(' Member@Example.ORG '));
        $digest = AccountRecordAccess::emailHmac('Member@Example.ORG');
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $digest);
        self::assertStringNotContainsString('member@example.org', $digest);
        self::assertSame($digest, AccountRecordAccess::emailHmac(' member@example.org '));
    }
}
