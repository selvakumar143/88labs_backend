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
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'transaction_hash' => 'required|string'
        ]);

        $lastId = WalletTopup::max('id') + 1;
        $requestId = 'TOP-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $data = WalletTopup::create([
            'request_id' => $requestId,
            'client_id' => Auth::id(),
            'amount' => $request->amount,
            'transaction_hash' => $request->transaction_hash,
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
