<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WalletTopupController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'request_amount' => 'nullable|numeric|min:0.01|required_without:amount',
            'service_fee' => 'nullable|numeric|min:0',
            'transaction_hash' => 'required|string'
        ]);

        $requestAmount = (float) ($validated['request_amount'] ?? $validated['amount']);
        $serviceFee = round((float) ($validated['service_fee'] ?? 0), 2);

        $lastId = WalletTopup::max('id') + 1;
        $requestId = 'TOP-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $data = WalletTopup::create([
            'request_id' => $requestId,
            'client_id' => Auth::id(),
            'amount' => $requestAmount,
            'request_amount' => $requestAmount,
            'service_fee' => $serviceFee,
            'transaction_hash' => $validated['transaction_hash'],
            'status' => WalletTopup::STATUS_PENDING
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'wallet_topup_created',
            title: 'New Wallet Topup Request',
            message: Auth::user()->name . " submitted wallet topup {$data->request_id}.",
            meta: [
                'wallet_topup_id' => $data->id,
                'request_id' => $data->request_id,
                'client_id' => Auth::id(),
                'client_name' => Auth::user()->name,
                'amount' => $data->amount,
                'request_amount' => $data->request_amount,
                'service_fee' => $data->service_fee,
                'status' => $data->status,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request submitted',
            'data' => $data
        ]);
    }

    public function myRequests(Request $request)
    {
        $query = WalletTopup::where('client_id', Auth::id());

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate($request->integer('per_page', 10))
        ]);
    }
}
