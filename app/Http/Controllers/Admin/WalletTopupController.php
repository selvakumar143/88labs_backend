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
            'client.client',
            'client.tenantClient',
            'clientProfileByUserId',
            'clientProfileByClientId',
            'creatorUser:id,name',
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
                    ->orWhere('request_amount', 'like', "%{$search}%")
                    ->orWhere('service_fee', 'like', "%{$search}%")
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
            $item->client_id = $this->resolveClientOwnerUserId($item);
            $item->client_name = $this->resolveClientName($item);
            $item->created_by = optional($item->creatorUser)->name ?? optional($item->client)->name;
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
                client: $topup->client,
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
            'client.client',
            'client.tenantClient',
            'clientProfileByUserId',
            'clientProfileByClientId',
            'creatorUser:id,name',
        ]);
        $updatedTopup->client_id = $this->resolveClientOwnerUserId($updatedTopup);
        $updatedTopup->client_name = $this->resolveClientName($updatedTopup);
        $updatedTopup->created_by = optional($updatedTopup->creatorUser)->name ?? optional($updatedTopup->client)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request status updated.',
            'data' => $updatedTopup,
        ]);
    }

    private function resolveClientOwnerUserId(WalletTopup $topup): ?int
    {
        return optional($topup->clientProfileByUserId)->primary_admin_user_id
            ?? optional($topup->clientProfileByClientId)->primary_admin_user_id
            ?? optional(optional($topup->client)->tenantClient)->primary_admin_user_id
            ?? $topup->client_id;
    }

    private function resolveClientName(WalletTopup $topup): ?string
    {
        return optional($topup->clientProfileByUserId)->clientName
            ?? optional($topup->clientProfileByClientId)->clientName
            ?? optional(optional($topup->client)->client)->clientName
            ?? optional(optional($topup->client)->tenantClient)->clientName
            ?? optional($topup->client)->name;
    }
}
