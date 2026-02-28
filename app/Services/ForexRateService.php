<?php

namespace App\Services;

use Carbon\Carbon;

class ForexRateService
{
    public function getLatestRates(): array
    {
        return $this->buildStaticResponse();
    }

    public function refreshRates(): array
    {
        /*
         * Live API call is intentionally commented for now.
         * Uncomment this block when you need real-time rates again.
         *
         * $response = Http::timeout((int) config('services.forex.http_timeout', 15))
         *     ->get(config('services.forex.base_url'), [
         *         'api_key' => config('services.forex.api_key'),
         *         'base' => config('services.forex.base', 'USDT'),
         *         'currencies' => config('services.forex.currencies', 'USD,EUR'),
         *     ]);
         *
         * if ($response->successful()) {
         *     $payload = $response->json();
         *     if (is_array($payload) && ($payload['success'] ?? false)) {
         *         $timestamp = Carbon::createFromTimestampUTC((int) ($payload['timestamp'] ?? time()));
         *
         *         return [
         *             'source' => 'live_api',
         *             'refresh_interval' => 'hourly',
         *             'base' => $payload['base'] ?? config('services.forex.base', 'USDT'),
         *             'timestamp_unix' => (int) ($payload['timestamp'] ?? time()),
         *             'timestamp_readable_utc' => $timestamp->copy()->toDateTimeString() . ' UTC',
         *             'timestamp_readable_local' => $timestamp->copy()->setTimezone(config('app.timezone'))->toDateTimeString() . ' ' . config('app.timezone'),
         *             'rates' => $payload['rates'] ?? [],
         *         ];
         *     }
         * }
         */
        return $this->buildStaticResponse();
    }

    private function buildStaticResponse(): array
    {
        $nowTimestamp = time();
        $timestamp = Carbon::createFromTimestampUTC($nowTimestamp);

        return [
            'source' => 'static',
            'refresh_interval' => 'hourly',
            'base' => 'USDT',
            'timestamp_unix' => $nowTimestamp,
            'timestamp_readable_utc' => $timestamp->copy()->toDateTimeString() . ' UTC',
            'timestamp_readable_local' => $timestamp->copy()->setTimezone(config('app.timezone'))->toDateTimeString() . ' ' . config('app.timezone'),
            'rates' => [
                'EUR' => 0.8475810125,
                'USD' => 1.000135,
            ],
        ];
    }
}
