<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopRequest;
use App\Models\User;
use App\Models\WalletTopup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:all,wallet_topup,account_topup,exchange_request',
            'client_id' => 'nullable',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

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
            'type' => 'nullable|in:all,wallet_topup,account_topup,exchange_request',
            'client_id' => 'nullable',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:csv,pdf',
        ]);

        $format = $validated['format'] ?? 'csv';
        $transactions = $this->buildTransactions($validated)->values();

        if ($transactions->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No transactions found for export.',
            ], 404);
        }

        if ($format === 'pdf') {
            $html = $this->buildExportHtml($transactions);
            $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
            return $pdf->download('transactions_' . now()->format('Ymd_His') . '.pdf');
        }

        $headers = [
            'transaction_type',
            'reference',
            'client_id',
            'client_name',
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
        ];

        return response()->streamDownload(function () use ($transactions, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($transactions as $row) {
                fputcsv($handle, [
                    $row['transaction_type'] ?? '',
                    $row['reference'] ?? '',
                    $row['client_id'] ?? '',
                    $row['client_name'] ?? '',
                    $row['business_name'] ?? '',
                    $row['account_id'] ?? '',
                    $row['account_name'] ?? '',
                    $row['business_manager_name'] ?? '',
                    $row['amount'] ?? '',
                    $row['currency'] ?? '',
                    $row['status'] ?? '',
                    $row['payment_mode'] ?? '',
                    $row['transaction_hash'] ?? '',
                    $row['created_at'] ?? '',
                ]);
            }

            fclose($handle);
        }, 'transactions_' . now()->format('Ymd_His') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
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

        return $walletItems
            ->concat($accountTopupItems)
            ->concat($exchangeItems)
            ->sortByDesc('created_at_ts')
            ->values()
            ->map(function ($item) {
                unset($item['created_at_ts']);
                return $item;
            });
    }

    protected function walletTransactions(array $filters): Collection
    {
        $query = WalletTopup::with(['client']);

        if (!empty($filters['client_id']) && $filters['client_id'] !== 'all') {
            $query->where('client_id', $filters['client_id']);
        }

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
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest()->get()->map(function (WalletTopup $item) {
            return [
                'transaction_type' => 'wallet_topup',
                'reference' => $item->request_id ?? ('WALLET-' . $item->id),
                'client_id' => $item->client_id,
                'client_name' => optional($item->client)->name,
                'business_name' => null,
                'account_id' => null,
                'account_name' => null,
                'business_manager_name' => null,
                'amount' => (string) $item->amount,
                'currency' => $item->currency,
                'status' => $item->status,
                'payment_mode' => $item->payment_mode,
                'transaction_hash' => $item->transaction_hash,
                'created_at' => optional($item->created_at)?->toDateTimeString(),
                'created_at_ts' => optional($item->created_at)?->timestamp ?? 0,
            ];
        });
    }

    protected function accountTopupTransactions(array $filters): Collection
    {
        $query = TopRequest::with([
            'client:id,name',
            'adAccountRequest:id,request_id,business_name,business_manager_id,account_management_id',
            'adAccountRequest.businessManager:id,name',
            'adAccountRequest.accountManagement:id,account_id,name,business_manager_id',
            'adAccountRequest.accountManagement.businessManager:id,name',
        ]);

        if (!empty($filters['client_id']) && $filters['client_id'] !== 'all') {
            $query->where('client_id', $filters['client_id']);
        }

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
                    ->orWhereHas('client', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('adAccountRequest', function ($sub) use ($search) {
                        $sub->where('request_id', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->latest()->get()->map(function (TopRequest $item) {
            $adAccount = $item->adAccountRequest;
            $account = optional($adAccount)->accountManagement;

            return [
                'transaction_type' => 'account_topup',
                'reference' => 'TOP-' . $item->id,
                'client_id' => $item->client_id,
                'client_name' => optional($item->client)->name,
                'business_name' => optional($adAccount)->business_name,
                'account_id' => optional($account)->account_id,
                'account_name' => optional($account)->name,
                'business_manager_name' => optional(optional($adAccount)->businessManager)->name
                    ?? optional(optional($account)->businessManager)->name,
                'amount' => (string) $item->amount,
                'currency' => $item->currency,
                'status' => $item->status,
                'payment_mode' => null,
                'transaction_hash' => null,
                'created_at' => optional($item->created_at)?->toDateTimeString(),
                'created_at_ts' => optional($item->created_at)?->timestamp ?? 0,
            ];
        });
    }

    protected function exchangeTransactions(array $filters): Collection
    {
        if (!Schema::hasTable('exchange_requests')) {
            return collect();
        }

        $query = DB::table('exchange_requests');

        if (!empty($filters['client_id']) && $filters['client_id'] !== 'all') {
            $query->where('client_id', $filters['client_id']);
        }

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
                foreach (['request_id', 'amount', 'request_amount', 'from_currency', 'to_currency', 'based_cur', 'convertion_cur'] as $column) {
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
        $clientNames = User::whereIn('id', $clientIds)->pluck('name', 'id');

        return $rows->map(function ($row) use ($clientNames) {
            $createdAt = data_get($row, 'created_at');
            $timestamp = $createdAt ? strtotime((string) $createdAt) : 0;

            return [
                'transaction_type' => 'exchange_request',
                'reference' => data_get($row, 'request_id', 'EXCH-' . data_get($row, 'id')),
                'client_id' => data_get($row, 'client_id'),
                'client_name' => $clientNames[data_get($row, 'client_id')] ?? null,
                'business_name' => null,
                'account_id' => data_get($row, 'account_id'),
                'account_name' => data_get($row, 'account_name'),
                'business_manager_name' => data_get($row, 'business_manager_name'),
                'amount' => (string) data_get($row, 'request_amount', data_get($row, 'amount', '')),
                'currency' => data_get(
                    $row,
                    'convertion_cur',
                    data_get($row, 'to_currency', data_get($row, 'currency', data_get($row, 'from_currency', data_get($row, 'based_cur'))))
                ),
                'status' => data_get($row, 'status'),
                'payment_mode' => null,
                'transaction_hash' => data_get($row, 'transaction_hash'),
                'created_at' => $createdAt,
                'created_at_ts' => $timestamp ?: 0,
            ];
        });
    }

    protected function buildExportHtml(Collection $rows): string
    {
        $html = '
            <h2 style="text-align:center;">Transactions Report</h2>
            <table width="100%" border="1" cellspacing="0" cellpadding="6">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Client</th>
                        <th>Business</th>
                        <th>Account ID</th>
                        <th>Account Name</th>
                        <th>Business Manager</th>
                        <th>Amount</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th>Date Time</th>
                    </tr>
                </thead>
                <tbody>
        ';

        foreach ($rows as $index => $row) {
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . e((string) ($row['transaction_type'] ?? '')) . '</td>
                    <td>' . e((string) ($row['reference'] ?? '')) . '</td>
                    <td>' . e((string) ($row['client_name'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['business_name'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['account_id'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['account_name'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['business_manager_name'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['amount'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['currency'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['status'] ?? '-')) . '</td>
                    <td>' . e((string) ($row['created_at'] ?? '-')) . '</td>
                </tr>
            ';
        }

        $html .= '</tbody></table>';

        return $html;
    }
}
