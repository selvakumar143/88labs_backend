<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdAccountRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = AdAccountRequest::with([
            'client.client',
            'clientProfileByUserId',
            'clientProfileByClientId',
            'businessManager',
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
            $query->where(function ($q) use ($request) {
                $q->where('request_id', 'like', "%{$request->search}%")
                  ->orWhereHas('client', function ($sub) use ($request) {
                      $sub->where('name', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('client.client', function ($sub) use ($request) {
                      $sub->where('clientName', 'like', "%{$request->search}%");
                  })
                  ->orWhereHas('clientProfileByClientId', function ($sub) use ($request) {
                      $sub->where('clientName', 'like', "%{$request->search}%");
                  });
            });
        }

        $data = $query->latest()->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = optional($item->clientProfileByUserId)->clientName
                ?? optional($item->clientProfileByClientId)->clientName
                ?? optional(optional($item->client)->client)->clientName
                ?? optional($item->client)->name;
            $item->business_manager_name = optional($item->businessManager)->name;
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
                AdAccountRequest::STATUS_APPROVED,
                AdAccountRequest::STATUS_REJECTED,
                AdAccountRequest::STATUS_PENDING,
            ])],
            'business_manager_id' => ['sometimes', 'nullable', 'exists:business_managers,id'],
            'account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_preference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'card_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'card_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $requestData = AdAccountRequest::findOrFail($id);
        $previousStatus = $requestData->status;

        DB::transaction(function () use ($validated, $requestData) {
            $updateData = [
                'status' => $validated['status'],
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ];

            if (array_key_exists('business_manager_id', $validated)) {
                $updateData['business_manager_id'] = $validated['business_manager_id'];
            }

            if (array_key_exists('account_name', $validated)) {
                $updateData['account_name'] = $validated['account_name'];
            }

            if (array_key_exists('account_preference', $validated)) {
                $updateData['account_preference'] = $validated['account_preference'];
            }

            if (array_key_exists('account_id', $validated)) {
                $updateData['account_id'] = $validated['account_id'];
            }

            if (array_key_exists('card_type', $validated)) {
                $updateData['card_type'] = $validated['card_type'];
            }

            if (array_key_exists('card_number', $validated)) {
                $updateData['card_number'] = $validated['card_number'];
            }

            $requestData->update($updateData);
        });

        if ($previousStatus !== $validated['status']) {
            NotificationDispatcher::notifyClient(
                client: $requestData->client,
                eventType: 'ad_account_request_status_updated',
                title: 'Ad Account Request Updated',
                message: "Your ad account request {$requestData->request_id} is {$validated['status']}.",
                meta: [
                    'ad_account_request_id' => $requestData->id,
                    'request_id' => $requestData->request_id,
                    'status' => $validated['status'],
                ]
            );
        }

        $updatedRequest = $requestData->fresh([
            'client.client',
            'clientProfileByUserId',
            'clientProfileByClientId',
            'businessManager',
        ]);
        $updatedRequest->client_name = optional($updatedRequest->clientProfileByUserId)->clientName
            ?? optional($updatedRequest->clientProfileByClientId)->clientName
            ?? optional(optional($updatedRequest->client)->client)->clientName
            ?? optional($updatedRequest->client)->name;
        $updatedRequest->business_manager_name = optional($updatedRequest->businessManager)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Request status updated.',
            'data' => $updatedRequest,
        ]);
    }
}
