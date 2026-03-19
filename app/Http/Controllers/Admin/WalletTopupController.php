<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class WalletTopupController extends Controller
{
    public function index(Request $request)
    {
        $query = WalletTopup::with([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
        ]);

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
                    ->orWhere('request_amount', 'like', "%{$search}%")
                    ->orWhere('service_fee', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($clientQuery) use ($search) {
                        $clientQuery->where('clientName', 'like', "%{$search}%")
                            ->orWhere('client_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $data = $query->latest()->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = $this->resolveClientName($item);
            $item->created_by = optional($item->creatorUser)->name
                ?? optional(optional($item->client)->primaryAdmin)->name
                ?? $item->client_name;
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
        $previousStatus = $topup->status;

        $topup->update([
            'status' => $validated['status'],
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);

        if ($previousStatus !== $validated['status']) {
            NotificationDispatcher::notifyClient(
                client: optional($topup->client)->primaryAdmin,
                eventType: 'wallet_topup_status_updated',
                title: 'Wallet Topup Request Updated',
                message: "Your wallet topup request {$topup->request_id} is {$validated['status']}.",
                meta: [
                    'wallet_topup_id' => $topup->id,
                    'request_id' => $topup->request_id,
                    'status' => $validated['status'],
                ]
            );
        }

        $updatedTopup = $topup->fresh([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
        ]);
        $updatedTopup->client_name = $this->resolveClientName($updatedTopup);
        $updatedTopup->created_by = optional($updatedTopup->creatorUser)->name
            ?? optional(optional($updatedTopup->client)->primaryAdmin)->name
            ?? $updatedTopup->client_name;

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request status updated.',
            'data' => $updatedTopup,
        ]);
    }

    private function resolveClientName(WalletTopup $topup): ?string
    {
        return data_get($topup, 'client.clientName')
            ?? data_get($topup, 'client.client_name')
            ?? data_get($topup, 'client.name');
    }
}
