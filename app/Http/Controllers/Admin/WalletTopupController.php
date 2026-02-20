<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use Illuminate\Support\Facades\Auth;

class WalletTopupController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => 'success',
            'data' => WalletTopup::with('client')->latest()->get()
        ]);
    }

    public function approve($id)
    {
        $topup = WalletTopup::findOrFail($id);

        $topup->update([
            'status' => WalletTopup::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return response()->json(['status'=>'success']);
    }

    public function reject($id)
    {
        $topup = WalletTopup::findOrFail($id);

        $topup->update([
            'status' => WalletTopup::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return response()->json(['status'=>'success']);
    }
}