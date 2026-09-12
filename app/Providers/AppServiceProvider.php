<?php

namespace App\Providers;

use App\Mail\Transport\GmailApiTransport;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('gmail-api', function (array $config = []): GmailApiTransport {
            return new GmailApiTransport(
                $this->app->make(HttpFactory::class),
                $this->app->make('cache.store'),
                $this->app->make(Encrypter::class),
                (string) ($config['client_id'] ?? ''),
                (string) ($config['client_secret'] ?? ''),
                (string) ($config['refresh_token'] ?? ''),
                (string) ($config['sender'] ?? ''),
                (int) ($config['timeout'] ?? 20),
            );
        });
    }
}
