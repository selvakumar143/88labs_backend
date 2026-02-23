<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\WalletTopup;
use App\Models\AdAccountRequest;

class ClientDashboardController extends Controller
{
    public function dashboard(Request $request)
    {
        $clientId = Auth::id();
    
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
        $year      = $request->input('year', now()->year);
    
        /*
        |--------------------------------------------------------------------------
        | BASE QUERY (Approved Only)
        |--------------------------------------------------------------------------
        */
    
        $baseQuery = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED);
    
        if ($startDate) {
            $baseQuery->whereDate('approved_at', '>=', Carbon::parse($startDate));
        }
    
        if ($endDate) {
            $baseQuery->whereDate('approved_at', '<=', Carbon::parse($endDate));
        }
    
        /*
        |--------------------------------------------------------------------------
        | 1️⃣ TOTAL BALANCE (USD + EUR)
        |--------------------------------------------------------------------------
        */
    
        $balances = (clone $baseQuery)
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');
    
        /*
        |--------------------------------------------------------------------------
        | 2️⃣ ACTIVE APPROVED TOPUPS TOTAL AMOUNT
        |--------------------------------------------------------------------------
        */

        $approvedTopupsTotal = (clone $baseQuery)->sum('amount');
            
        /*
        |--------------------------------------------------------------------------
        | 3️⃣ ACTIVE AD ACCOUNTS COUNT
        |--------------------------------------------------------------------------
        */

        $activeAdAccounts = AdAccountRequest::where('client_id', $clientId)
            ->where('status', 'approved')
            ->count();
            
        /*
        |--------------------------------------------------------------------------
        | 4️⃣ MONTHLY USD LINE CHART
        |--------------------------------------------------------------------------
        */
    
        $monthlyRaw = (clone $baseQuery)
            ->where('currency', 'USD')
            ->whereYear('approved_at', $year)
            ->selectRaw('MONTH(approved_at) as month')
            ->selectRaw('SUM(amount) as total_amount')
            ->groupBy(DB::raw('MONTH(approved_at)'))
            ->pluck('total_amount', 'month')
            ->toArray();
    
        $monthlyData = [];
    
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[] = [
                'month' => Carbon::create()->month($i)->format('M'),
                'total' => (float) ($monthlyRaw[$i] ?? 0),
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | 5️⃣ RECENT TOPUPS TABLE
        |--------------------------------------------------------------------------
        */
    
        $recentTopups = DB::table('wallet_topups')
        ->leftJoin('ad_account_requests', 'wallet_topups.client_id', '=', 'ad_account_requests.client_id')
        ->where('wallet_topups.client_id', $clientId)
        ->orderByDesc('wallet_topups.id')
        ->limit(5)
        ->select(
            'wallet_topups.request_id',
            'wallet_topups.amount',
            'wallet_topups.status',
            'wallet_topups.created_at',
            'wallet_topups.approved_at',
            'ad_account_requests.business_name'
        )
        ->get()
        ->map(function ($item) {
            return [
                'txn_id'     => $item->request_id,
                'ad_account' => $item->business_name ?? '-',
                'amount'     => (float) $item->amount,
                'status'     => strtoupper($item->status),
                'date'       => Carbon::parse(
                    $item->approved_at ?? $item->created_at
                )->format('d/m/Y'),
            ];
        });
    
        /*
        |--------------------------------------------------------------------------
        | FINAL JSON RESPONSE
        |--------------------------------------------------------------------------
        */
    
        return response()->json([
            'status' => 'success',
            'data' => [
                'approved_topups_total' => (float) $approvedTopupsTotal,
                'active_ad_accounts' => $activeAdAccounts,
                'monthly_usd_chart' => $monthlyData,
                'recent_topups' => $recentTopups,
                'total_balance' => [
                    'USD' => (float) ($balances['USD'] ?? 0),
                    'EUR' => (float) ($balances['EUR'] ?? 0),
                ],
            ]
        ]);
    }
}