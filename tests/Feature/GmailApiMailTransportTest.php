<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class GmailApiMailTransportTest extends TestCase
{
    public function test_it_sends_rfc_message_through_gmail_api_and_reuses_cached_access_token(): void
    {
        config([
            'mail.default' => 'gmail-api',
            'mail.from.address' => 'info@iuoamc.uk',
            'mail.from.name' => 'IUOAMC',
            'mail.mailers.gmail-api' => [
                'transport' => 'gmail-api',
                'client_id' => 'client-id.apps.googleusercontent.com',
                'client_secret' => 'test-client-secret',
                'refresh_token' => 'test-refresh-token',
                'sender' => 'info@iuoamc.uk',
                'timeout' => 20,
            ],
        ]);

        app('mail.manager')->forgetMailers();
        $tokenCalls = 0;
        $sendCalls = 0;

        Http::fake(function (Request $request) use (&$tokenCalls, &$sendCalls) {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $tokenCalls++;
                $this->assertSame('refresh_token', $request['grant_type']);
                $this->assertSame('test-refresh-token', $request['refresh_token']);

                return Http::response([
                    'access_token' => 'test-access-token',
                    'expires_in' => 3600,
                    'token_type' => 'Bearer',
                ]);
            }

            $this->assertSame(
                'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
                $request->url(),
            );
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer test-access-token'));

            $encoded = (string) $request['raw'];
            $padding = str_repeat('=', (4 - strlen($encoded) % 4) % 4);
            $mime = base64_decode(strtr($encoded, '-_', '+/').$padding, true);

            $this->assertIsString($mime);
            $this->assertStringContainsString('recipient@example.com', $mime);
            $this->assertStringContainsString('Secure delivery test', $mime);
            $sendCalls++;

            return Http::response(['id' => 'gmail-message-'.$sendCalls]);
        });

        foreach ([1, 2] as $attempt) {
            Mail::mailer('gmail-api')->raw(
                'Secure delivery test '.$attempt,
                fn (Message $message) => $message
                    ->to('recipient@example.com')
                    ->subject('IUOAMC secure mail'),
            );
        }

        $this->assertSame(1, $tokenCalls);
        $this->assertSame(2, $sendCalls);
    }

    public function test_readiness_command_reports_only_readiness_markers(): void
    {
        config([
            'mail.default' => 'gmail-api',
            'mail.from.address' => 'info@iuoamc.uk',
            'mail.mailers.gmail-api' => [
                'transport' => 'gmail-api',
                'client_id' => 'private-client-id',
                'client_secret' => 'private-client-secret',
                'refresh_token' => 'private-refresh-token',
                'sender' => 'info@iuoamc.uk',
                'timeout' => 20,
            ],
        ]);

        $this->artisan('iuoamc:gmail-api-readiness')
            ->expectsOutput('DEFAULT_MAILER=READY')
            ->expectsOutput('CLIENT_ID=READY')
            ->expectsOutput('CLIENT_SECRET=READY')
            ->expectsOutput('REFRESH_TOKEN=READY')
            ->expectsOutput('SENDER=READY')
            ->expectsOutput('FROM_MATCHES_SENDER=READY')
            ->assertSuccessful();
    }
}
