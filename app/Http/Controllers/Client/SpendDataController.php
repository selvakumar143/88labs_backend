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
            'business_name' => 'nullable|string',
            'account_name' => 'nullable|string',
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

        if (!empty($validated['business_name'])) {
            $businessName = trim((string) $validated['business_name']);
            $query->where('aar.business_name', 'like', "%{$businessName}%");
        }

        if (!empty($validated['account_name'])) {
            $accountName = trim((string) $validated['account_name']);
            $query->where('aar.account_name', 'like', "%{$accountName}%");
        }

        if (!empty($validated['date_start'])) {
            $query->whereDate('g.date_start', '>=', $validated['date_start']);
        }

        if (!empty($validated['date_end'])) {
            $query->whereDate('g.date_stop', '<=', $validated['date_end']);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);

        $items = $query
            ->orderByDesc('g.date_start')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }

    public function summary(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
        ]);

        $clientId = (int) $request->attributes->get('current_client_id');

        $spendQuery = DB::table('get_spend_data as g')
            ->selectRaw('SUM(g.spend) as total_spend')
            ->where('g.client_id', $clientId)
            ->when(!empty($validated['account_id']) && $validated['account_id'] !== 'all', function ($query) use ($validated) {
                $query->where('g.account_id', $validated['account_id']);
            })
            ->when(!empty($validated['date_start']), function ($query) use ($validated) {
                $query->whereDate('g.date_start', '>=', $validated['date_start']);
            })
            ->when(!empty($validated['date_end']), function ($query) use ($validated) {
                $query->whereDate('g.date_stop', '<=', $validated['date_end']);
            });

        $totalSpend = (float) ($spendQuery->value('total_spend') ?? 0);

        $client = DB::table('clients as c')
            ->leftJoin('users as u', 'u.id', '=', 'c.primary_admin_user_id')
            ->where('c.id', $clientId)
            ->select([
                'c.id as client_id',
                DB::raw("COALESCE(c.clientName, u.name, 'Unknown') as client_name"),
                DB::raw('ROUND(COALESCE(c.serviceFeePercent, 0), 2) as service_fee'),
            ])
            ->first();

        $serviceFee = (float) (data_get($client, 'service_fee') ?? 0);
        $totalProfit = round($totalSpend * $serviceFee / 100, 2);

        return response()->json([
            'status' => 'success',
            'data' => [
                'client_id' => data_get($client, 'client_id', $clientId),
                'client_name' => data_get($client, 'client_name', 'Unknown'),
                'service_fee' => $serviceFee,
                'total_spending' => round($totalSpend, 2),
                'total_spend_usd' => round($totalSpend, 2),
                'total_profit' => $totalProfit,
            ],
        ]);
    }
}
