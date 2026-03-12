<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Symfony\Component\Mailer\Transport;

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
        Mail::extend('sendgrid', function (array $config = []) {
            $dsn = $config['dsn'] ?? env('SENDGRID_DSN');

            if (! $dsn) {
                $key = $config['api_key'] ?? env('SENDGRID_API_KEY');

                if (! $key) {
                    throw new InvalidArgumentException('SendGrid is not configured. Set SENDGRID_DSN or SENDGRID_API_KEY.');
                }

                $scheme = $config['scheme'] ?? env('SENDGRID_SCHEME', 'sendgrid+api');
                $host = $config['host'] ?? env('SENDGRID_HOST', 'default');
                $region = $config['region'] ?? env('SENDGRID_REGION');
                $query = $region ? '?region='.rawurlencode($region) : '';

                $dsn = sprintf('%s://%s@%s%s', $scheme, rawurlencode($key), $host, $query);
            }

            return Transport::fromDsn($dsn);
        });
    }
}
