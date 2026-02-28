<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\ForexRateService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('forex:refresh', function (ForexRateService $forexRateService) {
    try {
        $data = $forexRateService->refreshRates();
        $this->info('Forex rates refreshed at ' . $data['timestamp_readable_utc']);
    } catch (\Throwable $e) {
        $this->error('Failed to refresh forex rates: ' . $e->getMessage());
        return 1;
    }

    return 0;
})->purpose('Refresh forex rates from external API');
