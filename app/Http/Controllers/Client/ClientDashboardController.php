<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\WalletTopup;
use App\Models\AdAccountRequest;
use App\Models\TopRequest;

class ClientDashboardController extends Controller
{
    private function getWalletBalances(int $clientId, ?string $startDate = null, ?string $endDate = null): array
    {
        $walletTopupQuery = WalletTopup::where('client_id', $clientId)
            ->whereRaw('LOWER(status) = ?', [WalletTopup::STATUS_APPROVED]);

        if ($startDate) {
            $walletTopupQuery->whereDate('approved_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $walletTopupQuery->whereDate('approved_at', '<=', Carbon::parse($endDate));
        }

        $walletTopupsByCurrency = $walletTopupQuery
            ->selectRaw('UPPER(currency) as currency, SUM(amount) as total')
            ->groupBy(DB::raw('UPPER(currency)'))
            ->pluck('total', 'currency');

        $adTopupQuery = TopRequest::where('client_id', $clientId)
            ->whereRaw('LOWER(status) = ?', [TopRequest::STATUS_APPROVED]);

        if ($startDate) {
            $adTopupQuery->whereDate('updated_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $adTopupQuery->whereDate('updated_at', '<=', Carbon::parse($endDate));
        }

        $adTopupsByCurrency = $adTopupQuery
            ->selectRaw('UPPER(currency) as currency, SUM(amount) as total')
            ->groupBy(DB::raw('UPPER(currency)'))
            ->pluck('total', 'currency');

        $currencyTotals = [];
        $allCurrencies = collect($walletTopupsByCurrency->keys())
            ->merge($adTopupsByCurrency->keys())
            ->unique()
            ->values();

        foreach ($allCurrencies as $currency) {
            $credited = (float) ($walletTopupsByCurrency[$currency] ?? 0);
            $deducted = (float) ($adTopupsByCurrency[$currency] ?? 0);
            $currencyTotals[$currency] = $credited - $deducted;
        }

        return [
            'usd_total' => (float) ($currencyTotals['USD'] ?? 0),
            'eur_total' => (float) ($currencyTotals['EUR'] ?? 0),
            'currency_totals' => $currencyTotals,
        ];
    }

    private function getTotalActiveAdsAccount(int $clientId): int
    {
        return (int) AdAccountRequest::where('client_id', $clientId)
            ->whereRaw('LOWER(status) = ?', [AdAccountRequest::STATUS_APPROVED])
            ->sum('number_of_accounts');
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN DASHBOARD
    | /client/dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $clientId = Auth::id();
        $year = (int) $request->input('year', now()->year);

        $walletBalances = $this->getWalletBalances($clientId);

        /*
        |--------------------------------------------------------------------------
        | MONTHLY TOTAL AMOUNT (USD ONLY - BASED ON APPROVAL DATE)
        |--------------------------------------------------------------------------
        */
        $monthlyRaw = WalletTopup::where('client_id', $clientId)
            ->whereRaw('LOWER(status) = ?', [WalletTopup::STATUS_APPROVED])
            ->whereRaw('UPPER(currency) = ?', ['USD'])
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

        $totalActiveAdsAccount = $this->getTotalActiveAdsAccount($clientId);
    
        /*
        |--------------------------------------------------------------------------
        | FINAL RESPONSE
        |--------------------------------------------------------------------------
        */
    
        return response()->json([
            'status' => 'success',
            'data' => [
                'summary' => [
                    'USD_total' => (float) ($walletBalances['usd_total'] ?? 0),
                    'EUR_total' => (float) ($walletBalances['eur_total'] ?? 0),
                    'currency_totals' => $walletBalances['currency_totals'] ?? [],
                    'total_active_ads_account' => (int) $totalActiveAdsAccount,
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

        $balances = $this->getWalletBalances($clientId, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => $balances
        ]);
    }

    public function walletSummary()
    {
        $clientId = Auth::id();
        $balances = $this->getWalletBalances($clientId);

        return response()->json([
            'status' => 'success',
            'data' => $balances,
        ]);
    }

    public function totalActiveAccounts()
    {
        $clientId = Auth::id();
        $totalActiveAdsAccount = $this->getTotalActiveAdsAccount($clientId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_active_ads_account' => $totalActiveAdsAccount,
            ],
        ]);
    }
}
