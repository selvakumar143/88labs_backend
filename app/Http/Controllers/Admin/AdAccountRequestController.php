<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class AdAccountRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = AdAccountRequest::with([
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
            ])],
        ]);

        $requestData = AdAccountRequest::findOrFail($id);

        $requestData->update([
            'status' => $validated['status'],
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        $updatedRequest = $requestData->fresh([
            'client.client',
            'clientProfileByUserId',
            'clientProfileByClientId',
        ]);
        $updatedRequest->client_name = optional($updatedRequest->clientProfileByUserId)->clientName
            ?? optional($updatedRequest->clientProfileByClientId)->clientName
            ?? optional(optional($updatedRequest->client)->client)->clientName
            ?? optional($updatedRequest->client)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Request status updated.',
            'data' => $updatedRequest,
        ]);
    }
}
