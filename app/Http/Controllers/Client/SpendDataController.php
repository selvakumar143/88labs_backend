<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpendDataController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|string',
            'business_manager_id' => 'nullable|integer',
            'business_name' => 'nullable|string',
            'account_name' => 'nullable|string',
            'search' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $clientId = (int) $request->attributes->get('current_client_id');

        $query = DB::table('get_spend_data as g')
            ->leftJoin('clients as c', 'c.id', '=', 'g.client_id')
            ->leftJoin('ad_account_requests as aar', function ($join) {
                $join->on('aar.account_id', '=', 'g.account_id')
                    ->on('aar.client_id', '=', 'g.client_id');
            })
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id')
            ->where('g.client_id', $clientId)
            ->select([
                'g.id',
                'g.client_id',
                DB::raw("COALESCE(c.clientName, 'Unknown') as client_name"),
                'g.account_id',
                'aar.account_name',
                'aar.business_name',
                DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
                'g.spend',
                'g.date_start',
                'g.date_stop',
                'g.created_at',
                'g.updated_at',
            ]);

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $query->where('g.account_id', $validated['account_id']);
        }

        if (!empty($validated['business_manager_id'])) {
            $query->where('aar.business_manager_id', $validated['business_manager_id']);
        }

        if (!empty($validated['business_name'])) {
            $businessName = trim((string) $validated['business_name']);
            $query->where('aar.business_name', 'like', "%{$businessName}%");
        }

        if (!empty($validated['account_name'])) {
            $accountName = trim((string) $validated['account_name']);
            $query->where('aar.account_name', 'like', "%{$accountName}%");
        }

        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $query->where(function ($q) use ($search) {
                $q->orWhere('g.account_id', 'like', "%{$search}%")
                    ->orWhere('aar.account_name', 'like', "%{$search}%")
                    ->orWhere('aar.business_name', 'like', "%{$search}%")
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

        $perPage = (int) ($validated['per_page'] ?? 10);

        $items = $query
            ->orderByDesc('g.created_at')
            ->paginate($perPage);

        $totalsQuery = DB::table('get_spend_data as g')
            ->leftJoin('ad_account_requests as aar', function ($join) {
                $join->on('aar.account_id', '=', 'g.account_id')
                    ->on('aar.client_id', '=', 'g.client_id');
            })
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id')
            ->where('g.client_id', $clientId);

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $totalsQuery->where('g.account_id', $validated['account_id']);
        }

        if (!empty($validated['business_manager_id'])) {
            $totalsQuery->where('aar.business_manager_id', $validated['business_manager_id']);
        }

        if (!empty($validated['business_name'])) {
            $businessName = trim((string) $validated['business_name']);
            $totalsQuery->where('aar.business_name', 'like', "%{$businessName}%");
        }

        if (!empty($validated['account_name'])) {
            $accountName = trim((string) $validated['account_name']);
            $totalsQuery->where('aar.account_name', 'like', "%{$accountName}%");
        }

        if (!empty($validated['search'])) {
            $search = trim((string) $validated['search']);
            $totalsQuery->where(function ($q) use ($search) {
                $q->orWhere('g.account_id', 'like', "%{$search}%")
                    ->orWhere('aar.account_name', 'like', "%{$search}%")
                    ->orWhere('aar.business_name', 'like', "%{$search}%")
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

        $totals = $totalsQuery->select([
            DB::raw('COUNT(*) as total_transactions'),
            DB::raw('COUNT(DISTINCT g.account_id) as total_accounts'),
            DB::raw('ROUND(COALESCE(SUM(g.spend), 0), 2) as total_spend'),
        ])->first();

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'totals' => [
                'total_accounts' => (int) ($totals->total_accounts ?? 0),
                'total_transactions' => (int) ($totals->total_transactions ?? 0),
                'total_spend' => (float) ($totals->total_spend ?? 0),
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|string',
            'business_manager_id' => 'nullable|integer',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $clientId = (int) $request->attributes->get('current_client_id');

        $perPage = (int) ($validated['per_page'] ?? 10);

        $spendByAccount = DB::table('get_spend_data as g')
            ->select([
                'g.account_id',
                DB::raw('SUM(g.spend) as total_spend'),
            ])
            ->where('g.client_id', $clientId)
            ->when(!empty($validated['date_start']), function ($query) use ($validated) {
                $query->whereDate('g.date_start', '>=', $validated['date_start']);
            })
            ->when(!empty($validated['date_end']), function ($query) use ($validated) {
                $query->whereDate('g.date_stop', '<=', $validated['date_end']);
            })
            ->groupBy('g.account_id');

        $summary = DB::table('ad_account_requests as aar')
            ->leftJoinSub($spendByAccount, 'spend', function ($join) {
                $join->on('aar.account_id', '=', 'spend.account_id');
            })
            ->leftJoin('business_managers as bm', 'bm.id', '=', 'aar.business_manager_id')
            ->where('aar.client_id', $clientId)
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('aar.account_id', $validated['account_id']);
            })
            ->when(!empty($validated['business_manager_id']), function ($query) use ($validated) {
                $query->where('aar.business_manager_id', $validated['business_manager_id']);
            })
            ->select([
                'aar.account_id',
                'aar.account_name',
                'bm.id as business_manager_id',
                DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
                DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spend'),
            ])
            ->orderByDesc('total_spend')
            ->paginate($perPage);

        $accounts = DB::table('ad_account_requests')
            ->where('client_id', $clientId)
            ->select([
                'account_id',
                'account_name',
            ])
            ->distinct()
            ->orderBy('account_name')
            ->get();

        $businessManagers = DB::table('business_managers as bm')
            ->leftJoin('ad_account_requests as aar', 'aar.business_manager_id', '=', 'bm.id')
            ->where('aar.client_id', $clientId)
            ->select([
                'bm.id as business_manager_id',
                DB::raw("COALESCE(bm.name, '-') as business_manager_name"),
            ])
            ->distinct()
            ->orderBy('business_manager_name')
            ->get();

        $totalAccounts = DB::table('ad_account_requests')
            ->where('client_id', $clientId)
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('account_id', $validated['account_id']);
            })
            ->when(!empty($validated['business_manager_id']), function ($query) use ($validated) {
                $query->where('business_manager_id', $validated['business_manager_id']);
            })
            ->distinct('account_id')
            ->count('account_id');

        $totalManagers = DB::table('ad_account_requests')
            ->where('client_id', $clientId)
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('account_id', $validated['account_id']);
            })
            ->when(!empty($validated['business_manager_id']), function ($query) use ($validated) {
                $query->where('business_manager_id', $validated['business_manager_id']);
            })
            ->distinct('business_manager_id')
            ->count('business_manager_id');

        $totalSpend = (float) (DB::table('get_spend_data as g')
            ->leftJoin('ad_account_requests as aar', function ($join) {
                $join->on('aar.account_id', '=', 'g.account_id')
                    ->on('aar.client_id', '=', 'g.client_id');
            })
            ->where('g.client_id', $clientId)
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('g.account_id', $validated['account_id']);
            })
            ->when(!empty($validated['business_manager_id']), function ($query) use ($validated) {
                $query->where('aar.business_manager_id', $validated['business_manager_id']);
            })
            ->when(!empty($validated['date_start']), function ($query) use ($validated) {
                $query->whereDate('g.date_start', '>=', $validated['date_start']);
            })
            ->when(!empty($validated['date_end']), function ($query) use ($validated) {
                $query->whereDate('g.date_stop', '<=', $validated['date_end']);
            })
            ->sum('g.spend') ?? 0);

        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => $summary,
                'accounts' => $accounts,
                'business_managers' => $businessManagers,
                'totals' => [
                    'total_accounts' => $totalAccounts,
                    'total_spend' => round($totalSpend, 2),
                    'total_manager_count' => $totalManagers,
                ],
            ],
        ]);
    }
}
