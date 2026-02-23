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
        $year = $request->input('year', now()->year);
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
    
        /*
        |--------------------------------------------------------------------------
        | 1️⃣ Summary Counts
        |--------------------------------------------------------------------------
        */
    
        $approvedTopupsCount = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED)
            ->count();
    
        $activeAdAccountsCount = AdAccountRequest::where('client_id', $clientId)
            ->where('status', AdAccountRequest::STATUS_APPROVED)
            ->count();
    
    
        /*
        |--------------------------------------------------------------------------
        | 2️⃣ Monthly Topups (Graph)
        |--------------------------------------------------------------------------
        */
    
        $monthlyRaw = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED)
            ->whereYear('approved_at', $year)
            ->select(
                DB::raw('MONTH(approved_at) as month'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('month')
            ->pluck('total_amount', 'month');
    
        $monthlyData = [];
    
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[] = [
                'month' => Carbon::create()->month($i)->format('M'),
                'total' => $monthlyRaw[$i] ?? 0
            ];
        }
    
    
        /*
        |--------------------------------------------------------------------------
        | 3️⃣ Recent Topups (Latest 5)
        |--------------------------------------------------------------------------
        */
    
        $recentTopups = DB::table('wallet_topups')
            ->leftJoin('ad_account_requests', 'wallet_topups.client_id', '=', 'ad_account_requests.client_id')
            ->where('wallet_topups.client_id', $clientId)
            ->select(
                'wallet_topups.request_id',
                'wallet_topups.amount',
                'wallet_topups.status',
                'wallet_topups.created_at',
                'wallet_topups.approved_at',
                'ad_account_requests.business_name'
            )
            ->orderByDesc('wallet_topups.id')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'txn_id'     => $item->request_id,
                    'ad_account' => $item->business_name ?? '-',
                    'amount'     => $item->amount,
                    'status'     => $item->status === 'approved'
                                    ? 'DONE'
                                    : strtoupper($item->status),
                    'date'       => Carbon::parse(
                                        $item->approved_at ?? $item->created_at
                                    )->format('d/m/Y'),
                ];
            });
    
    
        /*
        |--------------------------------------------------------------------------
        | 4️⃣ Wallet Balance (USD / EUR with date filter)
        |--------------------------------------------------------------------------
        */
    
        $walletQuery = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED);
    
        if ($startDate && $endDate) {
            $walletQuery->whereBetween('approved_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }
    
        $balances = $walletQuery
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');
    
    
        /*
        |--------------------------------------------------------------------------
        | Final Response
        |--------------------------------------------------------------------------
        */
    
        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'approved_wallet_topups_count' => $approvedTopupsCount,
                    'active_ad_accounts_count' => $activeAdAccountsCount,
                ],
                'wallet' => [
                    'USD' => $balances['USD'] ?? 0,
                    'EUR' => $balances['EUR'] ?? 0,
                ],
                'monthly_topups' => $monthlyData,
                'recent_topups' => $recentTopups
            ]
        ]);
    }
}