<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use App\Models\WalletTopup;
use App\Models\AdAccountRequest;
use App\Models\ExchangeRequest;
use App\Models\TopRequest;
use App\Models\GetSpendData;

class ClientDashboardController extends Controller
{
    public function showLogin()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Client login route is available.',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();

        if (!$user || !$user->hasAnyRole(['customer', 'Customer', 'client_admin', 'client_manager', 'client_viewer'])) {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Not a client user.',
            ], 403);
        }

        if ($user->status !== 'active') {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Your account is inactive.',
            ], 403);
        }

        $tenantClient = $user->tenantClient()->with('primaryAdmin:id,status')->first()
            ?? $user->client()->with('primaryAdmin:id,status')->first();

        if ($tenantClient && !$tenantClient->enabled) {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Your client account is disabled.',
            ], 403);
        }

        if ($tenantClient && $tenantClient->primaryAdmin && $tenantClient->primaryAdmin->status !== 'active') {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Your client admin account is inactive.',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

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
            ->whereIn(DB::raw('LOWER(status)'), [
                TopRequest::STATUS_PENDING,
                TopRequest::STATUS_APPROVED,
            ]);

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

        $exchangeOutByCurrency = collect();
        $exchangeInByCurrency = collect();

        if (Schema::hasTable('exchange_requests')) {
            $exchangeQuery = DB::table('exchange_requests')
                ->where('client_id', $clientId);

            if (Schema::hasColumn('exchange_requests', 'status')) {
                // Pending exchanges should reduce wallet (outgoing), but only approved exchanges add back (incoming).
                $exchangeOutQuery = (clone $exchangeQuery)->whereIn(DB::raw('LOWER(status)'), [
                    ExchangeRequest::STATUS_PENDING,
                    ExchangeRequest::STATUS_APPROVED,
                ]);
                $exchangeInQuery = (clone $exchangeQuery)->whereRaw('LOWER(status) = ?', [ExchangeRequest::STATUS_APPROVED]);
            } else {
                $exchangeOutQuery = clone $exchangeQuery;
                $exchangeInQuery = clone $exchangeQuery;
            }

            $exchangeDateColumns = [];
            foreach (['approved_at', 'updated_at', 'created_at'] as $candidate) {
                if (Schema::hasColumn('exchange_requests', $candidate)) {
                    $exchangeDateColumns[] = $candidate;
                }
            }

            if (!empty($exchangeDateColumns) && ($startDate || $endDate)) {
                $exchangeDateExpression = 'DATE(COALESCE(' . implode(', ', $exchangeDateColumns) . '))';

                if ($startDate) {
                    $exchangeOutQuery->whereRaw(
                        "{$exchangeDateExpression} >= ?",
                        [Carbon::parse($startDate)->toDateString()]
                    );
                    $exchangeInQuery->whereRaw(
                        "{$exchangeDateExpression} >= ?",
                        [Carbon::parse($startDate)->toDateString()]
                    );
                }

                if ($endDate) {
                    $exchangeOutQuery->whereRaw(
                        "{$exchangeDateExpression} <= ?",
                        [Carbon::parse($endDate)->toDateString()]
                    );
                    $exchangeInQuery->whereRaw(
                        "{$exchangeDateExpression} <= ?",
                        [Carbon::parse($endDate)->toDateString()]
                    );
                }
            }

            $baseCurrencyColumn = Schema::hasColumn('exchange_requests', 'base_currency')
                ? 'base_currency'
                : (Schema::hasColumn('exchange_requests', 'based_cur') ? 'based_cur' : null);

            $conversionCurrencyColumn = Schema::hasColumn('exchange_requests', 'converion_currency')
                ? 'converion_currency'
                : (Schema::hasColumn('exchange_requests', 'convertion_cur') ? 'convertion_cur' : null);

            if (Schema::hasColumn('exchange_requests', 'total_deduction')) {
                $exchangeDeductionExpression = 'COALESCE(total_deduction, request_amount + COALESCE(service_fee, 0))';
            } else {
                $exchangeDeductionExpression = 'request_amount + COALESCE(service_fee, 0)';
            }

            $exchangeReturnExpression = Schema::hasColumn('exchange_requests', 'return_amount')
                ? 'return_amount'
                : (Schema::hasColumn('exchange_requests', 'final_amount') ? 'final_amount' : null);

            if ($baseCurrencyColumn) {
                $exchangeOutByCurrency = (clone $exchangeOutQuery)
                    ->selectRaw("UPPER({$baseCurrencyColumn}) as currency, SUM({$exchangeDeductionExpression}) as total")
                    ->groupBy(DB::raw("UPPER({$baseCurrencyColumn})"))
                    ->pluck('total', 'currency');
            }

            if ($conversionCurrencyColumn && $exchangeReturnExpression) {
                $exchangeInByCurrency = (clone $exchangeInQuery)
                    ->selectRaw("UPPER({$conversionCurrencyColumn}) as currency, SUM({$exchangeReturnExpression}) as total")
                    ->groupBy(DB::raw("UPPER({$conversionCurrencyColumn})"))
                    ->pluck('total', 'currency');
            }
        }

        $currencyTotals = [];
        $allCurrencies = collect($walletTopupsByCurrency->keys())
            ->merge($adTopupsByCurrency->keys())
            ->merge($exchangeOutByCurrency->keys())
            ->merge($exchangeInByCurrency->keys())
            ->unique()
            ->values();

        foreach ($allCurrencies as $currency) {
            $walletTopupCredit = (float) ($walletTopupsByCurrency[$currency] ?? 0);
            $exchangeCredit = (float) ($exchangeInByCurrency[$currency] ?? 0);
            $accountTopupDeduction = (float) ($adTopupsByCurrency[$currency] ?? 0);
            $exchangeDeduction = (float) ($exchangeOutByCurrency[$currency] ?? 0);

            $currencyTotals[$currency] = ($walletTopupCredit + $exchangeCredit) - ($accountTopupDeduction + $exchangeDeduction);
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
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN DASHBOARD
    | /client/dashboard
    |--------------------------------------------------------------------------
    */

    public function dashboard(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');
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
        $totalSpendTrend = $this->GetSpendsTrend($clientId, $startDate, $endDate);
    
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
                'recent_topups' => $recentTopups,
                'totalSpendTrend' => $totalSpendTrend,
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
        $clientId = (int) $request->attributes->get('current_client_id');
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
        $clientId = app()->bound('currentClientId')
            ? (int) app('currentClientId')
            : (int) auth()->user()?->tenantClientId();
        $balances = $this->getWalletBalances($clientId);

        return response()->json([
            'status' => 'success',
            'data' => $balances,
        ]);
    }

    public function totalActiveAccounts()
    {
        $clientId = app()->bound('currentClientId')
            ? (int) app('currentClientId')
            : (int) auth()->user()?->tenantClientId();
        $totalActiveAdsAccount = $this->getTotalActiveAdsAccount($clientId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_active_ads_account' => $totalActiveAdsAccount,
            ],
        ]);
    }

    public function GetSpendsTrend($clientId='',$startDate,$endDate)
    {

        $query = GetSpendData::query();

        $query->where('client_id', $clientId);

        if (!empty($startDate)) {
            $query->whereDate('date_start', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('date_stop', '<=', $endDate);
        }

        $perPage = 20;

        $items = $query
            ->orderByDesc('date_start')->get();
            
        return $items;
    }
}
