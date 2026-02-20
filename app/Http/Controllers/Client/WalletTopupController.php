<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
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

        $exists = WalletTopup::where('client_id', Auth::id())
                    ->where('status', WalletTopup::STATUS_PENDING)
                    ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have a pending topup request.'
            ], 400);
        }

        $lastId = WalletTopup::max('id') + 1;
        $requestId = 'TOP-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $data = WalletTopup::create([
            'request_id' => $requestId,
            'client_id' => Auth::id(),
            'amount' => $request->amount,
            'transaction_hash' => $request->transaction_hash,
            'status' => WalletTopup::STATUS_PENDING
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request submitted',
            'data' => $data
        ]);
    }

    public function myRequests()
    {
        return response()->json([
            'status' => 'success',
            'data' => WalletTopup::where('client_id', Auth::id())->latest()->get()
        ]);
    }
}