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
    /*
    |--------------------------------------------------------------------------
    | MAIN DASHBOARD
    | /client/dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $clientId = Auth::id();
        $year = $request->input('year', now()->year);
        
        /*
        |--------------------------------------------------------------------------
        | 1️⃣ SUMMARY → TOTAL APPROVED AMOUNT (USD + EUR)
        |--------------------------------------------------------------------------
        */
    
        $approvedTotals = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED)
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');
    
        /*
        |--------------------------------------------------------------------------
        | MONTHLY TOTAL AMOUNT (USD ONLY - BASED ON APPROVAL DATE)
        |--------------------------------------------------------------------------
        */
        $year = 2026; // or now()->year if needed

        $monthlyRaw = WalletTopup::where('status', WalletTopup::STATUS_APPROVED)
            ->where('currency', 'USD')
            ->whereNotNull('approved_at')
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
        | 3️⃣ RECENT TOPUPS (Approved + Pending + Account Name)
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
        | FINAL RESPONSE
        |--------------------------------------------------------------------------
        */
    
        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'USD_total' => (float) ($approvedTotals['USD'] ?? 0),
                    'EUR_total' => (float) ($approvedTotals['EUR'] ?? 0),
                ],
                'monthly_topups' => $monthlyData,
                'recent_topups' => $recentTopups
            ]
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | WALLET ONLY (With Date Filter)
    | /client/dashboard/wallet
    |--------------------------------------------------------------------------
    */

    public function wallet(Request $request)
    {
        $clientId = Auth::id();
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED);

        if ($startDate) {
            $query->whereDate('approved_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->whereDate('approved_at', '<=', Carbon::parse($endDate));
        }

        $balances = $query
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return response()->json([
            'status' => 'success',
            'data' => [
                'USD' => (float) ($balances['USD'] ?? 0),
                'EUR' => (float) ($balances['EUR'] ?? 0),
            ]
        ]);
    }
}