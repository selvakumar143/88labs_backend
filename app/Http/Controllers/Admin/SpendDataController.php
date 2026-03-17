<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GetSpendData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpendDataController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'account_id' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = GetSpendData::query();

        if (!empty($validated['client_id']) && $validated['client_id'] !== 'all') {
            $query->where('client_id', $validated['client_id']);
        }

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $query->where('account_id', $validated['account_id']);
        }

        if (!empty($validated['date_start'])) {
            $query->whereDate('date_start', '>=', $validated['date_start']);
        }

        if (!empty($validated['date_end'])) {
            $query->whereDate('date_stop', '<=', $validated['date_end']);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);

        $items = $query
            ->orderByDesc('date_start')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    public function clientSummary(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'account_id' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'include_empty' => 'nullable|boolean',
        ]);

        $includeEmpty = (bool) ($validated['include_empty'] ?? false);

        $spendQuery = GetSpendData::query()
            ->selectRaw('client_id, SUM(spend) as total_spend')
            ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                $clientId = $validated['client_id'];
                $query->whereExists(function ($sub) use ($clientId) {
                    $sub->select(DB::raw(1))
                        ->from('clients as c')
                        ->where('c.id', $clientId)
                        ->where(function ($match) {
                            $match->whereColumn('c.primary_admin_user_id', 'get_spend_data.client_id')
                                ->orWhereColumn('c.id', 'get_spend_data.client_id');
                        });
                });
            })
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('account_id', $validated['account_id']);
            })
            ->when(!empty($validated['date_start']), function ($query) use ($validated) {
                $query->whereDate('date_start', '>=', $validated['date_start']);
            })
            ->when(!empty($validated['date_end']), function ($query) use ($validated) {
                $query->whereDate('date_stop', '<=', $validated['date_end']);
            })
            ->groupBy('client_id');

        $perPage = (int) ($validated['per_page'] ?? 10);

        if ($includeEmpty) {
            $summary = DB::table('clients as c')
                ->leftJoinSub($spendQuery, 'spend', function ($join) {
                    $join->on('c.primary_admin_user_id', '=', 'spend.client_id')
                        ->orOn('c.id', '=', 'spend.client_id');
                })
                ->leftJoin('users as u', 'u.id', '=', 'c.primary_admin_user_id')
                ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                    $query->where('c.id', $validated['client_id']);
                })
                ->select([
                    'c.id as client_id',
                    DB::raw("COALESCE(c.clientName, u.name, 'Unknown') as client_name"),
                    DB::raw('ROUND(COALESCE(c.serviceFeePercent, 0), 2) as service_fee_percent'),
                    DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spends'),
                    DB::raw('ROUND(COALESCE(spend.total_spend, 0), 2) as total_spends_usd'),
                    DB::raw('ROUND((COALESCE(spend.total_spend, 0) * COALESCE(c.serviceFeePercent, 0) / 100), 2) as total_profit'),
                ])
                ->orderByDesc('total_spends')
                ->paginate($perPage);
        } else {
            $summary = DB::query()
                ->fromSub($spendQuery, 'spend')
                ->leftJoin('clients as c', function ($join) {
                    $join->on('c.primary_admin_user_id', '=', 'spend.client_id')
                        ->orOn('c.id', '=', 'spend.client_id');
                })
                ->leftJoin('users as u', 'u.id', '=', 'spend.client_id')
                ->when(!empty($validated['client_id']) && $validated['client_id'] !== 'all', function ($query) use ($validated) {
                    $query->where('c.id', $validated['client_id']);
                })
                ->select([
                    'c.id as client_id',
                    DB::raw("COALESCE(c.clientName, u.name, 'Unknown') as client_name"),
                    DB::raw('ROUND(COALESCE(c.serviceFeePercent, 0), 2) as service_fee_percent'),
                    DB::raw('ROUND(spend.total_spend, 2) as total_spends'),
                    DB::raw('ROUND(spend.total_spend, 2) as total_spends_usd'),
                    DB::raw('ROUND((spend.total_spend * COALESCE(c.serviceFeePercent, 0) / 100), 2) as total_profit'),
                ])
                ->orderByDesc('total_spends')
                ->paginate($perPage);
        }

        return response()->json([
            'status' => 'success',
            'data' => $summary,
        ]);
    }
}
