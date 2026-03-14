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
        $tenantOwnerUserId = (int) $request->attributes->get('current_client_owner_user_id');

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'request_amount' => 'nullable|numeric|min:0.01|required_without:amount',
            'service_fee' => 'nullable|numeric|min:0',
            'transaction_hash' => 'required|string'
        ]);

        $requestAmount = (float) ($validated['request_amount'] ?? $validated['amount']);
        $serviceFee = round((float) ($validated['service_fee'] ?? 0), 2);

        $lastId = WalletTopup::max('id') + 1;
        $requestId = 'TOP-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $currency =$validated['currency'] ; // You can modify this to accept currency from the request if needed
        $data = WalletTopup::create([
            'request_id' => $requestId,
            'client_id' => $tenantOwnerUserId,
            'sub_user_id' => Auth::id(),
            "currency" => $currency,
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
                'client_id' => $tenantOwnerUserId,
                'client_name' => Auth::user()->name,
                'amount' => $data->amount,
                'request_amount' => $data->request_amount,
                'service_fee' => $data->service_fee,
                'status' => $data->status,
            ]
        );

        $data->load([
            'client.tenantClient',
            'clientProfileByUserId',
            'clientProfileByClientId',
            'creatorUser:id,name',
        ]);
        $data->client_id = $this->resolveClientOwnerUserId($data);
        $data->client_name = $this->resolveClientName($data);
        $data->created_by = optional($data->creatorUser)->name ?? optional($data->client)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request submitted',
            'data' => $data
        ]);
    }

    public function myRequests(Request $request)
    {
        $tenantOwnerUserId = (int) $request->attributes->get('current_client_owner_user_id');
        $query = WalletTopup::with([
            'client.tenantClient',
            'clientProfileByUserId',
            'clientProfileByClientId',
            'creatorUser:id,name',
        ])->where('client_id', $tenantOwnerUserId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
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
