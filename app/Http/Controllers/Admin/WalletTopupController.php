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
        $query = WalletTopup::with([
            'client.client',
            'clientProfileByUserId',
            'clientProfileByClientId',
        ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $clientId = $request->input('client_id', $request->input('client'));
        if (!empty($clientId) && $clientId !== 'all') {
            $query->where(function ($q) use ($clientId) {
                $q->where('client_id', $clientId)
                    ->orWhereHas('clientProfileByUserId', function ($sub) use ($clientId) {
                        $sub->where('id', $clientId);
                    });
            });
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
                    })
                    ->orWhereHas('client.client', function ($clientProfileQuery) use ($search) {
                        $clientProfileQuery->where('clientName', 'like', "%{$search}%");
                    })
                    ->orWhereHas('clientProfileByClientId', function ($clientProfileQuery) use ($search) {
                        $clientProfileQuery->where('clientName', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query->latest()->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = optional($item->clientProfileByUserId)->clientName
                ?? optional($item->clientProfileByClientId)->clientName
                ?? optional(optional($item->client)->client)->clientName
                ?? optional($item->client)->name;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
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

        $updatedTopup = $topup->fresh([
            'client.client',
            'clientProfileByUserId',
            'clientProfileByClientId',
        ]);
        $updatedTopup->client_name = optional($updatedTopup->clientProfileByUserId)->clientName
            ?? optional($updatedTopup->clientProfileByClientId)->clientName
            ?? optional(optional($updatedTopup->client)->client)->clientName
            ?? optional($updatedTopup->client)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request status updated.',
            'data' => $updatedTopup,
        ]);
    }
}
