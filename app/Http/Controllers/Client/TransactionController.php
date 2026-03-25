<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CsvExcelResponse;
use App\Models\TopRequest;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionController extends Controller
{
    use CsvExcelResponse;

    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:all,wallet,wallet_topup,ad_account,ad_account_topup,account_topup,exchange_request',
            'client_id' => 'nullable',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $validated['type'] = $this->normalizeType($validated['type'] ?? 'all');
        $transactions = $this->buildTransactions($validated);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $total = $transactions->count();

        $items = $transactions->forPage($page, $perPage)->values();
        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $paginator,
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:all,wallet,wallet_topup,ad_account,ad_account_topup,account_topup,exchange_request',
            'client_id' => 'nullable',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:csv,excel',
        ]);

        $format = $validated['format'] ?? 'csv';
        $validated['type'] = $this->normalizeType($validated['type'] ?? 'all');
        $transactions = $this->buildTransactions($validated)->values();

        if ($transactions->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No transactions found for export.',
            ], 404);
        }

        $headers = [
            'transaction_type',
            'request_id',
            'client_id',
            'client_name',
            'sub_user_id',
            'created_by',
            'business_name',
            'account_id',
            'account_name',
            'business_manager_name',
            'amount',
            'currency',
            'status',
            'payment_mode',
            'transaction_hash',
            'created_at',
            'approved_at',
        ];

        $rows = $transactions->map(fn ($row) => $this->mapTransactionExportRow($row))->toArray();

        return $this->exportCsvOrExcel(
            'client-transactions',
            $headers,
            $rows,
            $format
        );
    }

    protected function buildTransactions(array $filters): Collection
    {
        $type = $filters['type'] ?? 'all';

        $walletItems = collect();
        $accountTopupItems = collect();
        $exchangeItems = collect();

        if (in_array($type, ['all', 'wallet_topup'], true)) {
            $walletItems = $this->walletTransactions($filters);
        }

        if (in_array($type, ['all', 'account_topup'], true)) {
            $accountTopupItems = $this->accountTopupTransactions($filters);
        }

        if (in_array($type, ['all', 'exchange_request'], true)) {
            $exchangeItems = $this->exchangeTransactions($filters);
        }

        $items = $walletItems
            ->concat($accountTopupItems)
            ->concat($exchangeItems)
            ->sortByDesc('created_at_ts')
            ->values();

        $clientProfiles = $this->loadClientProfiles($items->pluck('client_id')->filter()->unique()->values()->all());

        return $items->map(function ($item) use ($clientProfiles) {
            $clientId = (string) ($item['client_id'] ?? '');
            $profile = $clientProfiles[$clientId] ?? null;

            $item['client_profile'] = $profile;
            $item['client_name'] = data_get($profile, 'client_name', $item['client_name'] ?? null);
            $item['transaction_type'] = $this->humanizeTransactionType((string) ($item['transaction_type'] ?? ''));

            unset($item['created_at_ts']);
            return $item;
        });
    }

    protected function walletTransactions(array $filters): Collection
    {
        $clientId = app()->bound('currentClientId')
            ? (int) app('currentClientId')
            : (int) auth()->user()?->tenantClientId();

        $query = WalletTopup::with(['client.primaryAdmin:id,name', 'creatorUser:id,name'])
            ->where('client_id', $clientId);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('transaction_hash', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhere('request_amount', 'like', "%{$search}%")
                    ->orWhere('service_fee', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%");
            });
        }

        return $query->orderByDesc('id')->get()->map(function (WalletTopup $item) {
            $requestAmount = (string) ($item->request_amount ?? $item->amount);
            $serviceFee = (string) ($item->service_fee ?? 0);
            $clientName = data_get($item, 'client.clientName')
                ?? data_get($item, 'client.client_name')
                ?? data_get($item, 'client.name');

            return [
                'transaction_type' => 'wallet_topup',
                'transaction_type_key' => 'wallet_topup',
                'id' => $item->id,
                'request_id' => $item->request_id ?? ('WALLET-' . $item->id),
                'client_id' => $item->client_id,
                'client_name' => $clientName,
                'sub_user_id' => $item->sub_user_id,
                'created_by' => optional($item->creatorUser)->name
                    ?? optional(optional($item->client)->primaryAdmin)->name
                    ?? $clientName,
                'business_name' => null,
                'account_id' => null,
                'account_name' => null,
                'business_manager_name' => null,
                'amount' => (string) $item->amount,
                'request_amount' => $requestAmount,
                'service_fee' => $serviceFee,
                'total_amount' => (string) $item->total_amount,
                'currency' => $item->currency,
                'status' => $item->status,
                'payment_mode' => $item->payment_mode,
                'transaction_hash' => $item->transaction_hash,
                'created_at' => optional($item->created_at)?->toDateTimeString(),
                'approved_at' => optional($item->approved_at)?->toDateTimeString(),
                'created_at_ts' => optional($item->created_at)?->timestamp ?? 0,
            ];
        });
    }

    protected function accountTopupTransactions(array $filters): Collection
    {
        $clientId = app()->bound('currentClientId')
            ? (int) app('currentClientId')
            : (int) auth()->user()?->tenantClientId();

        $query = TopRequest::with([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'adAccountRequest:id,request_id,business_name,business_manager_id,account_id,account_name,card_type,card_number',
            'adAccountRequest.businessManager:id,name',
        ])->where('client_id', $clientId);

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('amount', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhereHas('adAccountRequest', function ($sub) use ($search) {
                        $sub->where('request_id', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderByDesc('id')->get()->map(function (TopRequest $item) {
            $adAccount = $item->adAccountRequest;
            $clientName = data_get($item, 'client.clientName')
                ?? data_get($item, 'client.client_name')
                ?? data_get($item, 'client.name');

            return [
                'transaction_type' => 'account_topup',
                'transaction_type_key' => 'account_topup',
                'id' => $item->id,
                'request_id' => $item->request_id,
                'client_id' => $item->client_id,
                'client_name' => $clientName,
                'sub_user_id' => $item->sub_user_id,
                'created_by' => optional($item->creatorUser)->name
                    ?? optional(optional($item->client)->primaryAdmin)->name
                    ?? $clientName,
                'business_name' => optional($adAccount)->business_name,
                'account_id' => optional($adAccount)->account_id,
                'account_name' => optional($adAccount)->account_name,
                'business_manager_name' => optional(optional($adAccount)->businessManager)->name,
                'amount' => (string) $item->amount,
                'currency' => $item->currency,
                'status' => $item->status,
                'payment_mode' => null,
                'transaction_hash' => null,
                'created_at' => optional($item->created_at)?->toDateTimeString(),
                'approved_at' => optional($item->approved_at)?->toDateTimeString(),
                'created_at_ts' => optional($item->created_at)?->timestamp ?? 0,
            ];
        });
    }

    protected function exchangeTransactions(array $filters): Collection
    {
        if (!Schema::hasTable('exchange_requests')) {
            return collect();
        }

        $clientId = app()->bound('currentClientId')
            ? (int) app('currentClientId')
            : (int) auth()->user()?->tenantClientId();

        $query = DB::table('exchange_requests')
            ->where('client_id', $clientId);

        if (!empty($filters['status']) && $filters['status'] !== 'all' && Schema::hasColumn('exchange_requests', 'status')) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['start_date']) && Schema::hasColumn('exchange_requests', 'created_at')) {
            $query->whereDate('created_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date']) && Schema::hasColumn('exchange_requests', 'created_at')) {
            $query->whereDate('created_at', '<=', $filters['end_date']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $columns = [];
                foreach (['request_id', 'amount', 'request_amount', 'from_currency', 'to_currency', 'based_cur', 'base_currency', 'convertion_cur', 'converion_currency'] as $column) {
                    if (Schema::hasColumn('exchange_requests', $column)) {
                        $columns[] = $column;
                    }
                }

                foreach ($columns as $index => $column) {
                    if ($index === 0) {
                        $q->where($column, 'like', "%{$search}%");
                        continue;
                    }

                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        $rows = $query->orderByDesc('id')->get();
        $clientIds = $rows->pluck('client_id')->filter()->unique()->values();
        $clientProfiles = $this->loadClientProfiles($clientIds->all());
        $creatorIds = $rows->pluck('sub_user_id')->filter()->unique()->values();
        $creatorNames = $creatorIds->isEmpty()
            ? collect()
            : User::whereIn('id', $creatorIds)->pluck('name', 'id');

        return $rows->map(function ($row) use ($clientProfiles, $creatorNames) {
            $createdAt = data_get($row, 'created_at');
            $timestamp = $createdAt ? strtotime((string) $createdAt) : 0;
            $clientId = (string) data_get($row, 'client_id');
            $clientProfile = $clientProfiles[$clientId] ?? null;
            $clientName = data_get($clientProfile, 'client_name');

            return [
                'transaction_type' => 'exchange_request',
                'transaction_type_key' => 'exchange_request',
                'id' => data_get($row, 'id'),
                'request_id' => data_get($row, 'request_id', 'EXCH-' . data_get($row, 'id')),
                'client_id' => data_get($row, 'client_id'),
                'client_name' => $clientName,
                'sub_user_id' => data_get($row, 'sub_user_id'),
                'created_by' => $creatorNames[data_get($row, 'sub_user_id')] ?? $clientName,
                'business_name' => null,
                'account_id' => data_get($row, 'account_id'),
                'account_name' => data_get($row, 'account_name'),
                'business_manager_name' => data_get($row, 'business_manager_name'),
                'amount' => (string) data_get($row, 'request_amount', data_get($row, 'amount', '')),
                'currency' => data_get(
                    $row,
                    'converion_currency',
                    data_get($row, 'convertion_cur', data_get($row, 'to_currency', data_get($row, 'currency', data_get($row, 'from_currency', data_get($row, 'base_currency', data_get($row, 'based_cur'))))))
                ),
                'status' => data_get($row, 'status'),
                'payment_mode' => null,
                'transaction_hash' => data_get($row, 'transaction_hash'),
                'created_at' => $createdAt,
                'approved_at' => data_get($row, 'approved_at'),
                'created_at_ts' => $timestamp ?: 0,
            ];
        });
    }

    protected function loadClientProfiles(array $clientIds): array
    {
        if (empty($clientIds) || !Schema::hasTable('clients')) {
            return [];
        }

        $rows = DB::table('clients')
            ->whereIn('id', $clientIds)
            ->get();

        $profiles = [];
        foreach ($rows as $row) {
            $profile = $this->normalizeClientProfile($row);
            $clientId = (string) data_get($row, 'id', '');

            if ($clientId !== '' && !isset($profiles[$clientId])) {
                $profiles[$clientId] = $profile;
            }
        }

        return $profiles;
    }

    protected function normalizeClientProfile(object $row): array
    {
        return [
            'id' => data_get($row, 'id'),
            'user_id' => data_get($row, 'primary_admin_user_id', data_get($row, 'user_id')),
            'client_code' => data_get($row, 'clientCode', data_get($row, 'client_code')),
            'client_name' => data_get($row, 'clientName', data_get($row, 'client_name')),
            'email' => data_get($row, 'email'),
            'phone' => data_get($row, 'phone'),
            'country' => data_get($row, 'country'),
            'client_type' => data_get($row, 'clientType', data_get($row, 'client_type')),
            'settlement_currency' => data_get($row, 'settlementCurrency', data_get($row, 'settlement_currency')),
            'enabled' => data_get($row, 'enabled'),
        ];
    }

    protected function humanizeTransactionType(string $type): string
    {
        return match ($type) {
            'wallet_topup' => 'Wallet topup',
            'account_topup' => 'Account topup',
            'exchange_request' => 'Exchange request',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    protected function normalizeType(string $type): string
    {
        return match ($type) {
            'wallet' => 'wallet_topup',
            'ad_account', 'ad_account_topup' => 'account_topup',
            default => $type,
        };
    }

    private function mapTransactionExportRow(array $row): array
    {
        return [
            'transaction_type' => (string) ($row['transaction_type'] ?? ''),
            'request_id' => (string) ($row['request_id'] ?? ''),
            'client_id' => (string) ($row['client_id'] ?? ''),
            'client_name' => (string) ($row['client_name'] ?? ''),
            'sub_user_id' => (string) ($row['sub_user_id'] ?? ''),
            'created_by' => (string) ($row['created_by'] ?? ''),
            'business_name' => (string) ($row['business_name'] ?? ''),
            'account_id' => (string) ($row['account_id'] ?? ''),
            'account_name' => (string) ($row['account_name'] ?? ''),
            'business_manager_name' => (string) ($row['business_manager_name'] ?? ''),
            'amount' => (string) ($row['amount'] ?? ''),
            'currency' => (string) ($row['currency'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'payment_mode' => (string) ($row['payment_mode'] ?? ''),
            'transaction_hash' => (string) ($row['transaction_hash'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'approved_at' => (string) ($row['approved_at'] ?? ''),
        ];
    }

    private function resolveClientOwnerUserIdFromRaw(int $clientId): int
    {
        return $clientId;
    }
}
