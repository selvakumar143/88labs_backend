<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\ForexRateService;
use App\Models\AdAccountRequest;
use App\Jobs\FetchAdAccountSpendJob;

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

Artisan::command('spend:sync', function () {
    $pairs = AdAccountRequest::query()
        ->select(['client_id', 'account_id'])
        ->whereNotNull('account_id')
        ->where('account_id', '!=', '')
        ->distinct()
        ->get();

    $pairs = $pairs->filter(function ($pair) {
        $accountId = trim((string) $pair->account_id);

        if ($accountId === '') {
            return false;
        }

        $upper = strtoupper($accountId);
        if (str_contains($upper, 'YOUR_ACCOUNT_ID')) {
            return false;
        }

        return true;
    })->values();

    if ($pairs->isEmpty()) {
        $this->info('No ad account IDs found for spend sync.');
        return 0;
    }

    foreach ($pairs as $pair) {
        FetchAdAccountSpendJob::dispatch(
            clientId: (int) $pair->client_id,
            accountId: trim((string) $pair->account_id),
        );
    }

    $this->info("Dispatched {$pairs->count()} spend sync job(s).");
    return 0;
})->purpose('Dispatch spend sync jobs for all ad account IDs');
