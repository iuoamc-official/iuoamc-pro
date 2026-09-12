<?php

declare(strict_types=1);

namespace App\Mail\Transport;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Throwable;

final class GmailApiTransport extends AbstractTransport
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const SEND_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    private readonly string $clientId;

    private readonly string $clientSecret;

    private readonly string $refreshToken;

    private readonly string $sender;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly Encrypter $encrypter,
        string $clientId,
        string $clientSecret,
        string $refreshToken,
        string $sender,
        private readonly int $timeout = 20,
    ) {
        parent::__construct();

        $this->clientId = trim($clientId);
        $this->clientSecret = trim($clientSecret);
        $this->refreshToken = trim($refreshToken);
        $this->sender = strtolower(trim($sender));

        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new InvalidArgumentException('Gmail API credentials are incomplete.');
        }

        if (filter_var($this->sender, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Gmail API sender is invalid.');
        }

        if ($this->timeout < 5 || $this->timeout > 60) {
            throw new InvalidArgumentException('Gmail API timeout must be between 5 and 60 seconds.');
        }
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }

    protected function doSend(SentMessage $message): void
    {
        $envelopeSender = strtolower($message->getEnvelope()->getSender()->getAddress());

        if (! hash_equals($this->sender, $envelopeSender)) {
            throw new TransportException('Gmail API sender does not match the authorized account.');
        }

        $raw = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');
        $accessToken = $this->accessToken();
        $response = $this->sendMessage($accessToken, $raw);

        if ($response->status() === 401) {
            $this->cache->forget($this->cacheKey());
            $response = $this->sendMessage($this->accessToken(), $raw);
        }

        if (! $response->successful()) {
            throw new TransportException('Gmail API delivery failed with HTTP '.$response->status().'.');
        }

        $messageId = $response->json('id');

        if (! is_string($messageId) || trim($messageId) === '') {
            throw new TransportException('Gmail API accepted no message identifier.');
        }

        $message->setMessageId($messageId);
        $message->appendDebug('Gmail API accepted the message.');
    }

    private function accessToken(): string
    {
        $cached = $this->cache->get($this->cacheKey());

        if (is_string($cached) && $cached !== '') {
            try {
                $decrypted = $this->encrypter->decryptString($cached);

                if ($decrypted !== '') {
                    return $decrypted;
                }
            } catch (Throwable) {
                $this->cache->forget($this->cacheKey());
            }
        }

        try {
            $response = $this->http
                ->asForm()
                ->acceptJson()
                ->connectTimeout(min(10, $this->timeout))
                ->timeout($this->timeout)
                ->post(self::TOKEN_ENDPOINT, [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $this->refreshToken,
                    'grant_type' => 'refresh_token',
                ]);
        } catch (Throwable $exception) {
            throw new TransportException('Gmail API authentication connection failed.', 0, $exception);
        }

        if (! $response->successful()) {
            throw new TransportException('Gmail API authentication failed with HTTP '.$response->status().'.');
        }

        $accessToken = $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 3600);

        if (! is_string($accessToken) || trim($accessToken) === '') {
            throw new TransportException('Gmail API returned no access token.');
        }

        $this->cache->put(
            $this->cacheKey(),
            $this->encrypter->encryptString($accessToken),
            max(60, min(3300, $expiresIn - 120)),
        );

        return $accessToken;
    }

    private function sendMessage(string $accessToken, string $raw): Response
    {
        try {
            return $this->http
                ->withToken($accessToken)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(min(10, $this->timeout))
                ->timeout($this->timeout)
                ->post(self::SEND_ENDPOINT, ['raw' => $raw]);
        } catch (Throwable $exception) {
            throw new TransportException('Gmail API delivery connection failed.', 0, $exception);
        }
    }

    private function cacheKey(): string
    {
        return 'mail:gmail-api:'.hash('sha256', $this->clientId.'|'.$this->sender);
    }
}
