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
        $year      = $request->input('year');
    
        /*
        |--------------------------------------------------------------------------
        | BASE FILTERED QUERY (Approved Only)
        |--------------------------------------------------------------------------
        */
    
        $baseQuery = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED);
    
        // If date range exists → use it
        if ($startDate && $endDate) {
            $baseQuery->whereBetween('approved_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }
        // If only year exists → use year
        elseif ($year) {
            $baseQuery->whereYear('approved_at', $year);
        }
    
        // Clone for reuse
        $filteredTopups = (clone $baseQuery)->get();
    
        $hasData = $filteredTopups->count() > 0;
    
        /*
        |--------------------------------------------------------------------------
        | 1️⃣ TOTAL BALANCE
        |--------------------------------------------------------------------------
        */
    
        $balances = (clone $baseQuery)
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');
    
        $usdBalance = $hasData ? (float) ($balances['USD'] ?? 0) : null;
        $eurBalance = $hasData ? (float) ($balances['EUR'] ?? 0) : null;
    
        /*
        |--------------------------------------------------------------------------
        | 2️⃣ APPROVED TOPUPS TOTAL
        |--------------------------------------------------------------------------
        */
    
        $approvedTopupsTotal = $hasData
            ? (float) (clone $baseQuery)->sum('amount')
            : null;
    
        /*
        |--------------------------------------------------------------------------
        | 3️⃣ ACTIVE AD ACCOUNTS (FILTERED)
        |--------------------------------------------------------------------------
        */

        $accountQuery = AdAccountRequest::where('ad_account_requests.client_id', $clientId)
            ->where('ad_account_requests.status', 'approved');

        // If date range is provided
        if ($startDate && $endDate) {
            $accountQuery->whereBetween('ad_account_requests.approved_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }
        // Else if only year is provided
        elseif ($year) {
            $accountQuery->whereYear('ad_account_requests.approved_at', $year);
        }

        $activeAdAccounts = $accountQuery->count();
        $activeAdAccounts = $activeAdAccounts > 0 ? $activeAdAccounts : null;
    
        /*
        |--------------------------------------------------------------------------
        | 4️⃣ MONTHLY USD CHART
        |--------------------------------------------------------------------------
        */
    
        $monthlyRaw = (clone $baseQuery)
            ->where('currency', 'USD')
            ->selectRaw('MONTH(approved_at) as month')
            ->selectRaw('SUM(amount) as total')
            ->groupBy(DB::raw('MONTH(approved_at)'))
            ->pluck('total', 'month')
            ->toArray();
    
        $monthlyData = [];
    
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[] = [
                'month' => Carbon::create()->month($i)->format('M'),
                'total' => $hasData && isset($monthlyRaw[$i])
                    ? (float) $monthlyRaw[$i]
                    : null
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | 5️⃣ RECENT TOPUPS (FILTERED)
        |--------------------------------------------------------------------------
        */
    
        $recentQuery = WalletTopup::where('wallet_topups.client_id', $clientId);

        if ($startDate && $endDate) {
            $recentQuery->whereBetween('wallet_topups.approved_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        } elseif ($year) {
            $recentQuery->whereYear('wallet_topups.approved_at', $year);
        }
        
        $recentTopups = $recentQuery
            ->leftJoin(
                'ad_account_requests',
                'wallet_topups.client_id',
                '=',
                'ad_account_requests.client_id'
            )
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
                    'ad_account' => $item->business_name ?? null,
                    'amount'     => (float) $item->amount,
                    'status'     => strtoupper($item->status),
                    'date'       => Carbon::parse(
                        $item->approved_at ?? $item->created_at
                    )->format('d/m/Y'),
                ];
            });
    
        /*
        |--------------------------------------------------------------------------
        | FINAL RESPONSE
        |--------------------------------------------------------------------------
        */
    
        return response()->json([
            'status' => 'success',
            'data' => [
                'approved_topups_total' => $approvedTopupsTotal,
                'active_ad_accounts'    => $activeAdAccounts,
                'monthly_usd_chart'     => $monthlyData,
                'recent_topups'         => $recentTopups,
                'total_balance' => [
                    'USD' => $usdBalance,
                    'EUR' => $eurBalance,
                ],
            ]
        ]);
    }
}