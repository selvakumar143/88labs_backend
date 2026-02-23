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
    public function summary()
    {
        $clientId = Auth::id();

        // Count of admin approved wallet topups
        $approvedTopupsCount = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED)
            ->count();

        // Count of active (approved) ad accounts
        $activeAdAccountsCount = AdAccountRequest::where('client_id', $clientId)
            ->where('status', AdAccountRequest::STATUS_APPROVED)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'approved_wallet_topups_count' => $approvedTopupsCount,
                'active_ad_accounts_count' => $activeAdAccountsCount,
            ]
        ]);
    }
    public function monthlyTopups()
    {
        $clientId = auth()->id();
        $year = request()->input('year', now()->year);

        $topups = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED)
            ->whereYear('approved_at', $year)
            ->select(
                DB::raw('MONTH(approved_at) as month'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('month')
            ->pluck('total_amount', 'month');

        // Format all 12 months
        $monthlyData = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[] = [
                'month' => Carbon::create()->month($i)->format('M'),
                'total' => $topups[$i] ?? 0
            ];
        }

        return response()->json([
            'status' => 'success',
            'year' => $year,
            'data' => $monthlyData
        ]);
    }
    public function recentTopups(Request $request)
    {
        $clientId = auth()->id();

        $perPage = $request->input('per_page', 5); // default 5

        $topups = DB::table('wallet_topups')
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
            ->paginate($perPage);

        // Format response data
        $topups->getCollection()->transform(function ($item) {
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

        return response()->json([
            'status' => 'success',
            'data'   => $topups
        ]);
    }


    public function walletSummary(Request $request)
    {
        $clientId = Auth::id();

        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        $query = WalletTopup::where('client_id', $clientId)
            ->where('status', WalletTopup::STATUS_APPROVED);

        if ($startDate && $endDate) {
            $query->whereBetween('approved_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        $balances = $query
            ->select('currency', DB::raw('SUM(amount) as total'))
            ->groupBy('currency')
            ->pluck('total', 'currency');

        return response()->json([
            'status' => 'success',
            'data' => [
                'USD' => $balances['USD'] ?? 0,
                'EUR' => $balances['EUR'] ?? 0,
            ]
        ]);
    }
    public function addBalance(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|in:USD,EUR',
            'transaction_hash' => 'required|string'
        ]);

        $lastId = WalletTopup::max('id') + 1;
        $requestId = 'TOP-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $data = WalletTopup::create([
            'request_id' => $requestId,
            'client_id' => Auth::id(),
            'amount' => $request->amount,
            'currency' => $request->currency,
            'transaction_hash' => $request->transaction_hash,
            'status' => WalletTopup::STATUS_PENDING
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request submitted',
            'data' => $data
        ]);
    }
}