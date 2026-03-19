<?php

namespace App\Jobs;

use App\Models\GetSpendData;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAdAccountSpendJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $clientId,
        public readonly string $accountId,
    ) {
    }

    public function handle(): void
    {
        $baseUrl = rtrim((string) config('services.facebook_spend.base_url'), '/');
        $endpoint = trim((string) config('services.facebook_spend.endpoint', 'insights'), '/');
        $token = (string) config('services.facebook_spend.token');
        $fields = (string) config('services.facebook_spend.fields', 'spend,campaign_name,adset_name,ad_name');
        $datePreset = (string) config('services.facebook_spend.date_preset', 'last_month');
        $timeout = (int) config('services.facebook_spend.http_timeout', 30);
        $time_increment = (string) config('services.facebook_spend.time_increment', 1);

        if ($baseUrl === '' || $token === '' || $this->accountId === '') {
            Log::warning('Skipping spend fetch because config or account_id is missing.', [
                'client_id' => $this->clientId,
                'account_id' => $this->accountId,
            ]);
            return;
        }

        $url = "{$baseUrl}/act_{$this->accountId}/{$endpoint}";
        $isFirstRequest = true;
        $insertedRows = 0;

        Log::info('Starting Facebook spend fetch.', [
            'client_id' => $this->clientId,
            'account_id' => $this->accountId,
            'base_url' => $baseUrl,
            'endpoint' => $endpoint,
            'fields' => $fields,
            'date_preset' => $datePreset,
            'time_increment' => $time_increment
        ]);

        while ($url) {
            $request = Http::timeout($timeout)->acceptJson();
            $response = $isFirstRequest
                ? $request->get($url, [
                    'fields' => $fields,
                    'date_preset' => $datePreset,
                    'access_token' => $token,
                    'time_increment' => $time_increment,
                ])
                : $request->get($url);

            Log::info('Facebook spend request URL.', [
                'client_id' => $this->clientId,
                'account_id' => $this->accountId,
                'request_url' => $response->effectiveUri() ? (string) $response->effectiveUri() : null,
            ]);

            if ($response->failed()) {
                Log::error('Facebook spend fetch failed.', [
                    'client_id' => $this->clientId,
                    'account_id' => $this->accountId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                $response->throw();
            }

            $payload = $response->json();
            $rows = data_get($payload, 'data', []);

            Log::info('Facebook spend response received.', [
                'client_id' => $this->clientId,
                'account_id' => $this->accountId,
                'rows_count' => is_countable($rows) ? count($rows) : 0,
                'first_row' => is_array($rows) && isset($rows[0]) ? $rows[0] : null,
                'paging' => data_get($payload, 'paging'),
            ]);

            if (empty($rows)) {
                Log::warning('Facebook spend response returned no rows.', [
                    'client_id' => $this->clientId,
                    'account_id' => $this->accountId,
                    'payload' => $payload,
                ]);
            }

            foreach ($rows as $row) {
                $dateStart = data_get($row, 'date_start');
                $dateStop = data_get($row, 'date_stop');
                $spend = (float) data_get($row, 'spend', 0);

                $existing = GetSpendData::query()
                    ->where('client_id', $this->clientId)
                    ->where('account_id', $this->accountId)
                    ->whereDate('date_start', $dateStart)
                    ->whereDate('date_stop', $dateStop)
                    ->first();

                if ($existing) {
                    $existing->spend = $spend;
                    $existing->date_start = $dateStart;
                    $existing->date_stop = $dateStop;
                    $existing->save();
                } else {
                    GetSpendData::create([
                        'client_id' => $this->clientId,
                        'account_id' => $this->accountId,
                        'spend' => $spend,
                        'date_start' => $dateStart,
                        'date_stop' => $dateStop,
                    ]);
                }
                $insertedRows++;
            }

            $nextUrl = data_get($payload, 'paging.next');
            if (!is_string($nextUrl) || $nextUrl === '') {
                break;
            }

            $url = $nextUrl;
            $isFirstRequest = false;
        }

        Log::info('Finished Facebook spend fetch.', [
            'client_id' => $this->clientId,
            'account_id' => $this->accountId,
            'inserted_rows' => $insertedRows,
        ]);
    }
}
