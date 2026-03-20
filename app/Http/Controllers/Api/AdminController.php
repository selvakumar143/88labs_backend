<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use App\Models\WalletTopup;
use App\Models\User;
use App\Models\Client;
use App\Models\TopRequest;
use App\Models\GetSpendData;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Get admin dashboard statistics.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $totalOnboarded = Client::where('enabled', true)->count();
        $pendingApprovals = WalletTopup::where('status', 'pending')->count();
        $needReview = AdAccountRequest::where('status', 'pending')->count();
        $totalSpends = (float) GetSpendData::sum('spend');
        $live = Carbon::now()->toDateTimeString();

        // Operational Queue
        $walletTopupRequestsPending = WalletTopup::where('status', 'pending')->count();
        $adAccountRequestsPending = AdAccountRequest::where('status', 'pending')->count();
        $disabledClients = User::role('customer')->where('status', 'inactive')->count();

        // Today Highlights
        $newClientsAdded = Client::whereDate('created_at', Carbon::today())->count();
        $approvedTopups = (float) WalletTopup::where('status', WalletTopup::STATUS_APPROVED)
            ->whereDate('approved_at', Carbon::today())
            ->sum('amount');
        $averageServiceFeeValue = (float) Client::whereNotNull('serviceFeePercent')->avg('serviceFeePercent');
        $averageServiceFee = number_format($averageServiceFeeValue, 2) . '%';
        $totalSpendTrend = $this->GetSpendsTrend();

        return response()->json([
            'total_onboarded' => $totalOnboarded,
            'pending_approvals' => $pendingApprovals,
            'need_review' => $needReview,
            'total_spends' => $totalSpends,
            'live' => $live,
            'wallet_topup_requests_pending' => $walletTopupRequestsPending,
            'ad_account_requests_pending' => $adAccountRequestsPending,
            'disabled_clients' => $disabledClients,
            'new_clients_added' => $newClientsAdded,
            'approved_topups' => $approvedTopups,
            'average_service_fee' => $averageServiceFee,
            'total_spend_trend' => $totalSpendTrend,
        ]);
    }


     public function GetSpendsTrend()
    {

        $startDate = Carbon::today()->subMonths(10)->toDateString();
        $endDate = Carbon::today()->toDateString();

        $query = GetSpendData::query();

        if (!empty($startDate)) {
            $query->whereDate('date_start', '>=', $startDate);
        }

        if (!empty($endDate)) {
            $query->whereDate('date_stop', '<=', $endDate);
        }

        $items = $query
            ->orderByDesc('date_start')->get();
            
        return $items;
    }
}
