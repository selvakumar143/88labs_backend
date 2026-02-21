<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class WalletTopupController extends Controller
{
    public function index(Request $request)
    {
        $query = WalletTopup::with('client');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $clientId = $request->input('client_id', $request->input('client'));
        if (!empty($clientId) && $clientId !== 'all') {
            $query->where('client_id', $clientId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('transaction_hash', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate($request->integer('per_page', 10))
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                WalletTopup::STATUS_APPROVED,
                WalletTopup::STATUS_REJECTED,
            ])],
        ]);

        $topup = WalletTopup::findOrFail($id);

        $topup->update([
            'status' => $validated['status'],
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request status updated.',
            'data' => $topup->fresh(),
        ]);
    }
}
