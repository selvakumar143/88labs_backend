<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CsvExcelResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpendDataController extends Controller
{
    use CsvExcelResponse;
    public function index(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'client_name' => 'nullable|string',
            'business_name' => 'nullable|string',
            'account_name' => 'nullable|string',
            'account_id' => 'nullable|string',
            'group_by' => 'nullable|in:account_id,client_id,business_manager_id',
            'search' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = DB::table('get_spend_data as g')
            ->leftJoin('clients as c', 'c.id', '=', 'g.client_id')
            ->leftJoin('ad_account_requests as aar', function ($join) {
                $join->on('aar.account_id', '=', 'g.account_id')
                    ->on('aar.client_id', '=', 'g.client_id');
            })
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id')
            ->select([
                'g.id',
                'g.client_id',
                DB::raw("COALESCE(c.clientName, 'Unknown') as client_name"),
                'g.account_id',
                'aar.account_name',
                'aar.business_name',
                'bm.id as business_manager_id',
                DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
                'g.spend',
                'g.date_start',
                'g.date_stop',
                'g.created_at',
                'g.updated_at',
            ]);

        if (!empty($validated['client_id']) && $validated['client_id'] !== 'all') {
            $query->where('g.client_id', $validated['client_id']);
        }

        if (!empty($validated['client_name'])) {
            $clientName = trim((string) $validated['client_name']);
            $query->where('c.clientName', 'like', "%{$clientName}%");
        }

        if (!empty($validated['business_name'])) {
            $businessName = trim((string) $validated['business_name']);
            $query->where('aar.business_name', 'like', "%{$businessName}%");
        }

        if (!empty($validated['account_name'])) {
            $accountName = trim((string) $validated['account_name']);
            $query->where('aar.account_name', 'like', "%{$accountName}%");
        }

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $query->where('g.account_id', $validated['account_id']);
        }

        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($q) use ($search) {
                $q->orWhere('g.client_id', 'like', "%{$search}%")
                    ->orWhere('c.clientName', 'like', "%{$search}%")
                    ->orWhere('g.account_id', 'like', "%{$search}%")
                    ->orWhere('aar.account_name', 'like', "%{$search}%")
                    ->orWhere('bm.id', 'like', "%{$search}%")
                    ->orWhere('bm.name', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['date_start'])) {
            $query->whereDate('g.date_start', '>=', $validated['date_start']);
        }

        if (!empty($validated['date_end'])) {
            $query->whereDate('g.date_stop', '<=', $validated['date_end']);
        }

        if (!empty($validated['group_by'])) {
            switch ($validated['group_by']) {
                case 'client_id':
                    $query->select([
                        'g.client_id',
                        DB::raw("COALESCE(c.clientName, 'Unknown') as client_name"),
                        DB::raw('ROUND(SUM(g.spend), 2) as total_spend'),
                        DB::raw('MIN(g.date_start) as date_start'),
                        DB::raw('MAX(g.date_stop) as date_stop'),
                    ])->groupBy('g.client_id', 'c.clientName');
                    break;
                case 'account_id':
                    $query->select([
                        'g.account_id',
                        'aar.account_name',
                        DB::raw('ROUND(SUM(g.spend), 2) as total_spend'),
                        DB::raw('MIN(g.date_start) as date_start'),
                        DB::raw('MAX(g.date_stop) as date_stop'),
                    ])->groupBy('g.account_id', 'aar.account_name');
                    break;
                case 'business_manager_id':
                    $query->select([
                        'bm.id as business_manager_id',
                        DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
                        DB::raw('ROUND(SUM(g.spend), 2) as total_spend'),
                        DB::raw('MIN(g.date_start) as date_start'),
                        DB::raw('MAX(g.date_stop) as date_stop'),
                    ])->groupBy('bm.id', 'bm.name');
                    break;
            }
        }

        $perPage = (int) ($validated['per_page'] ?? 10);

        $items = $query
            ->orderByDesc('g.created_at')
            ->paginate($perPage);

        $clients = DB::table('clients')
            ->select([
                'id as client_id',
                DB::raw("COALESCE(clientName, 'Unknown') as client_name"),
            ])
            ->orderBy('client_name')
            ->get();

        $businessManagers = DB::table('business_managers')
            ->select([
                'id as business_manager_id',
                DB::raw("COALESCE(name, '-') as business_manager_name"),
            ])
            ->orderBy('business_manager_name')
            ->get();

        $accounts = DB::table('ad_account_requests')
            ->select([
                'account_id',
                'account_name',
            ])
            ->distinct()
            ->orderBy('account_name')
            ->get();

        $totalsQuery = DB::table('get_spend_data as g')
            ->leftJoin('clients as c', 'c.id', '=', 'g.client_id')
            ->leftJoin('ad_account_requests as aar', function ($join) {
                $join->on('aar.account_id', '=', 'g.account_id')
                    ->on('aar.client_id', '=', 'g.client_id');
            })
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id');

        if (!empty($validated['client_id']) && $validated['client_id'] !== 'all') {
            $totalsQuery->where('g.client_id', $validated['client_id']);
        }

        if (!empty($validated['client_name'])) {
            $clientName = trim((string) $validated['client_name']);
            $totalsQuery->where('c.clientName', 'like', "%{$clientName}%");
        }

        if (!empty($validated['business_name'])) {
            $businessName = trim((string) $validated['business_name']);
            $totalsQuery->where('aar.business_name', 'like', "%{$businessName}%");
        }

        if (!empty($validated['account_name'])) {
            $accountName = trim((string) $validated['account_name']);
            $totalsQuery->where('aar.account_name', 'like', "%{$accountName}%");
        }

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $totalsQuery->where('g.account_id', $validated['account_id']);
        }

        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $totalsQuery->where(function ($q) use ($search) {
                $q->orWhere('g.client_id', 'like', "%{$search}%")
                    ->orWhere('c.clientName', 'like', "%{$search}%")
                    ->orWhere('g.account_id', 'like', "%{$search}%")
                    ->orWhere('aar.account_name', 'like', "%{$search}%")
                    ->orWhere('bm.id', 'like', "%{$search}%")
                    ->orWhere('bm.name', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['date_start'])) {
            $totalsQuery->whereDate('g.date_start', '>=', $validated['date_start']);
        }

        if (!empty($validated['date_end'])) {
            $totalsQuery->whereDate('g.date_stop', '<=', $validated['date_end']);
        }

        $spendTotals = $totalsQuery->select([
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('ROUND(COALESCE(SUM(g.spend), 0), 2) as total_spend'),
        ])->first();

        $totalClients = DB::table('clients as c')
            ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                $query->where('c.id', $validated['client_id']);
            })
            ->when(!empty($validated['client_name']), function ($query) use ($validated) {
                $clientName = trim((string) $validated['client_name']);
                $query->where('c.clientName', 'like', "%{$clientName}%");
            })
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $search = trim((string) $validated['search']);
                $query->where(function ($q) use ($search) {
                    $q->orWhere('c.id', 'like', "%{$search}%")
                        ->orWhere('c.clientName', 'like', "%{$search}%");
                });
            })
            ->distinct('c.id')
            ->count('c.id');

        $totalAccounts = DB::table('ad_account_requests as aar')
            ->leftJoin('clients as c', 'c.id', '=', 'aar.client_id')
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id')
            ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                $query->where('aar.client_id', $validated['client_id']);
            })
            ->when(!empty($validated['client_name']), function ($query) use ($validated) {
                $clientName = trim((string) $validated['client_name']);
                $query->where('c.clientName', 'like', "%{$clientName}%");
            })
            ->when(!empty($validated['business_name']), function ($query) use ($validated) {
                $businessName = trim((string) $validated['business_name']);
                $query->where('aar.business_name', 'like', "%{$businessName}%");
            })
            ->when(!empty($validated['account_name']), function ($query) use ($validated) {
                $accountName = trim((string) $validated['account_name']);
                $query->where('aar.account_name', 'like', "%{$accountName}%");
            })
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('aar.account_id', $validated['account_id']);
            })
            ->when(!empty($validated['search']), function ($query) use ($validated) {
                $search = trim((string) $validated['search']);
                $query->where(function ($q) use ($search) {
                    $q->orWhere('aar.client_id', 'like', "%{$search}%")
                        ->orWhere('c.clientName', 'like', "%{$search}%")
                        ->orWhere('aar.account_id', 'like', "%{$search}%")
                        ->orWhere('aar.account_name', 'like', "%{$search}%")
                        ->orWhere('bm.id', 'like', "%{$search}%")
                        ->orWhere('bm.name', 'like', "%{$search}%");
                });
            })
            ->distinct('aar.account_id')
            ->count('aar.account_id');

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'filters' => [
                'clients' => $clients,
                'business_managers' => $businessManagers,
                'accounts' => $accounts,
            ],
            'totals' => [
                'total_clients' => (int) ($totalClients ?? 0),
                'total_accounts' => (int) ($totalAccounts ?? 0),
                'total_spend' => (float) ($spendTotals->total_spend ?? 0),
                'total_transactions' => (int) ($spendTotals->total_transactions ?? 0),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'client_name' => 'nullable|string',
            'business_name' => 'nullable|string',
            'account_name' => 'nullable|string',
            'account_id' => 'nullable|string',
            'group_by' => 'nullable|in:account_id,client_id,business_manager_id',
            'search' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'format' => 'nullable|in:csv,excel',
        ]);

        $groupBy = $validated['group_by'] ?? null;
        $format = $validated['format'] ?? 'csv';

        $query = DB::table('get_spend_data as g')
            ->leftJoin('clients as c', 'c.id', '=', 'g.client_id')
            ->leftJoin('ad_account_requests as aar', function ($join) {
                $join->on('aar.account_id', '=', 'g.account_id')
                    ->on('aar.client_id', '=', 'g.client_id');
            })
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id');

        if (!empty($validated['client_id']) && $validated['client_id'] !== 'all') {
            $query->where('g.client_id', $validated['client_id']);
        }

        if (!empty($validated['client_name'])) {
            $clientName = trim((string) $validated['client_name']);
            $query->where('c.clientName', 'like', "%{$clientName}%");
        }

        if (!empty($validated['business_name'])) {
            $businessName = trim((string) $validated['business_name']);
            $query->where('aar.business_name', 'like', "%{$businessName}%");
        }

        if (!empty($validated['account_name'])) {
            $accountName = trim((string) $validated['account_name']);
            $query->where('aar.account_name', 'like', "%{$accountName}%");
        }

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $query->where('g.account_id', $validated['account_id']);
        }

        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($q) use ($search) {
                $q->orWhere('g.client_id', 'like', "%{$search}%")
                    ->orWhere('c.clientName', 'like', "%{$search}%")
                    ->orWhere('g.account_id', 'like', "%{$search}%")
                    ->orWhere('aar.account_name', 'like', "%{$search}%")
                    ->orWhere('bm.id', 'like', "%{$search}%")
                    ->orWhere('bm.name', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['date_start'])) {
            $query->whereDate('g.date_start', '>=', $validated['date_start']);
        }

        if (!empty($validated['date_end'])) {
            $query->whereDate('g.date_stop', '<=', $validated['date_end']);
        }

        if (!empty($validated['group_by'])) {
            switch ($validated['group_by']) {
                case 'client_id':
                    $query->select([
                        'g.client_id',
                        DB::raw("COALESCE(c.clientName, 'Unknown') as client_name"),
                        DB::raw('ROUND(SUM(g.spend), 2) as total_spend'),
                        DB::raw('MIN(g.date_start) as date_start'),
                        DB::raw('MAX(g.date_stop) as date_stop'),
                    ])->groupBy('g.client_id', 'c.clientName');
                    break;
                case 'account_id':
                    $query->select([
                        'g.account_id',
                        'aar.account_name',
                        DB::raw('ROUND(SUM(g.spend), 2) as total_spend'),
                        DB::raw('MIN(g.date_start) as date_start'),
                        DB::raw('MAX(g.date_stop) as date_stop'),
                    ])->groupBy('g.account_id', 'aar.account_name');
                    break;
                case 'business_manager_id':
                    $query->select([
                        'bm.id as business_manager_id',
                        DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
                        DB::raw('ROUND(SUM(g.spend), 2) as total_spend'),
                        DB::raw('MIN(g.date_start) as date_start'),
                        DB::raw('MAX(g.date_stop) as date_stop'),
                    ])->groupBy('bm.id', 'bm.name');
                    break;
            }
        } else {
            $query->select([
                'g.id',
                'g.client_id',
                DB::raw("COALESCE(c.clientName, 'Unknown') as client_name"),
                'g.account_id',
                'aar.account_name',
                'aar.business_name',
                'bm.id as business_manager_id',
                DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
                'g.spend',
                'g.date_start',
                'g.date_stop',
                'g.created_at',
                'g.updated_at',
            ]);
        }

        $rows = $query->orderByDesc('g.created_at')->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No spend data found for export.',
            ], 404);
        }

        $headers = $this->getSpendDataExportHeaders($groupBy);

        return $this->exportCsvOrExcel(
            'admin-spend-data',
            $headers,
            $rows->map(fn ($row) => (array) $row)->toArray(),
            $format
        );
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'account_id' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);

        $spendQuery = DB::table('get_spend_data as g')
            ->selectRaw('g.client_id, SUM(g.spend) as total_spend')
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('g.account_id', $validated['account_id']);
            })
            ->when(!empty($validated['date_start']), function ($query) use ($validated) {
                $query->whereDate('g.date_start', '>=', $validated['date_start']);
            })
            ->when(!empty($validated['date_end']), function ($query) use ($validated) {
                $query->whereDate('g.date_stop', '<=', $validated['date_end']);
            })
            ->groupBy('g.client_id');

        $summary = DB::table('clients as c')
            ->leftJoinSub($spendQuery, 'spend', function ($join) {
                $join->on('c.id', '=', 'spend.client_id');
            })
            ->leftJoin('users as u', 'u.id', '=', 'c.primary_admin_user_id')
            ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                $query->where('c.id', $validated['client_id']);
            })
            ->select([
                'c.id as client_id',
                DB::raw("COALESCE(c.clientName, u.name, 'Unknown') as client_name"),
                DB::raw('ROUND(COALESCE(c.serviceFeePercent, 0), 2) as service_fee'),
                DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spending'),
                DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spend_usd'),
                DB::raw('ROUND((COALESCE(spend.total_spend, 0) * COALESCE(c.serviceFeePercent, 0) / 100), 2) as total_profit'),
            ])
            ->orderByDesc('total_spending')
            ->paginate($perPage);

        $totalsRow = DB::table('clients as c')
            ->leftJoinSub($spendQuery, 'spend', function ($join) {
                $join->on('c.id', '=', 'spend.client_id');
            })
            ->leftJoin('users as u', 'u.id', '=', 'c.primary_admin_user_id')
            ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                $query->where('c.id', $validated['client_id']);
            })
            ->select([
                DB::raw('COUNT(DISTINCT c.id) as total_clients'),
                DB::raw('ROUND(COALESCE(SUM(spend.total_spend), 0), 2) as total_spend'),
                DB::raw('ROUND(SUM(COALESCE(spend.total_spend, 0) * COALESCE(c.serviceFeePercent, 0) / 100), 2) as total_profit'),
            ])
            ->first();

        $clients = DB::table('clients')
            ->select([
                'id as client_id',
                DB::raw("COALESCE(clientName, 'Unknown') as client_name"),
            ])
            ->orderBy('client_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => $summary,
                'clients' => $clients,
                'totals' => [
                    'total_clients' => (int) ($totalsRow->total_clients ?? 0),
                    'total_spend' => (float) ($totalsRow->total_spend ?? 0),
                    'total_profit' => (float) ($totalsRow->total_profit ?? 0),
                ],
            ],
        ]);
    }

    public function summaryExport(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'account_id' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'format' => 'nullable|in:csv,excel',
        ]);

        $format = $validated['format'] ?? 'csv';

        $spendQuery = DB::table('get_spend_data as g')
            ->selectRaw('g.client_id, SUM(g.spend) as total_spend')
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('g.account_id', $validated['account_id']);
            })
            ->when(!empty($validated['date_start']), function ($query) use ($validated) {
                $query->whereDate('g.date_start', '>=', $validated['date_start']);
            })
            ->when(!empty($validated['date_end']), function ($query) use ($validated) {
                $query->whereDate('g.date_stop', '<=', $validated['date_end']);
            })
            ->groupBy('g.client_id');

        $summary = DB::table('clients as c')
            ->leftJoinSub($spendQuery, 'spend', function ($join) {
                $join->on('c.id', '=', 'spend.client_id');
            })
            ->leftJoin('users as u', 'u.id', '=', 'c.primary_admin_user_id')
            ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                $query->where('c.id', $validated['client_id']);
            })
            ->select([
                'c.id as client_id',
                DB::raw("COALESCE(c.clientName, u.name, 'Unknown') as client_name"),
                DB::raw('ROUND(COALESCE(c.serviceFeePercent, 0), 2) as service_fee'),
                DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spending'),
                DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spend_usd'),
                DB::raw('ROUND((COALESCE(spend.total_spend, 0) * COALESCE(c.serviceFeePercent, 0) / 100), 2) as total_profit'),
            ])
            ->orderByDesc('total_spending')
            ->get();

        if ($summary->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No spend summary data available for export.',
            ], 404);
        }

        return $this->exportCsvOrExcel(
            'admin-spend-summary',
            [
                'client_id',
                'client_name',
                'service_fee',
                'total_spending',
                'total_spend_usd',
                'total_profit',
            ],
            $summary->map(fn ($row) => (array) $row)->toArray(),
            $format
        );
    }

    private function getSpendDataExportHeaders(?string $groupBy): array
    {
        switch ($groupBy) {
            case 'client_id':
                return ['client_id', 'client_name', 'total_spend', 'date_start', 'date_stop'];
            case 'account_id':
                return ['account_id', 'account_name', 'total_spend', 'date_start', 'date_stop'];
            case 'business_manager_id':
                return ['business_manager_id', 'business_manager_name', 'total_spend', 'date_start', 'date_stop'];
            default:
                return [
                    'client_id',
                    'client_name',
                    'account_id',
                    'account_name',
                    'business_name',
                    'business_manager_id',
                    'business_manager_name',
                    'spend',
                    'date_start',
                    'date_stop',
                    'created_at',
                    'updated_at',
                ];
        }
    }
}
