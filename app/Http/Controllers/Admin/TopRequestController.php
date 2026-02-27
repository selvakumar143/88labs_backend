<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TopRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = TopRequest::with([
            'client:id,name,email',
            'adAccountRequest:id,request_id,business_name,platform,status',
        ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id') && $request->client_id !== 'all') {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('ad_account_request_id') && $request->ad_account_request_id !== 'all') {
            $query->where('ad_account_request_id', $request->ad_account_request_id);
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('amount', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($sub) use ($search) {
                        $sub->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('adAccountRequest', function ($sub) use ($search) {
                        $sub->where('request_id', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%")
                            ->orWhere('platform', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate($request->integer('per_page', 10)),
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                TopRequest::STATUS_PENDING,
                TopRequest::STATUS_APPROVED,
            ])],
        ]);

        $topRequest = TopRequest::findOrFail($id);
        $previousStatus = $topRequest->status;
        $topRequest->update([
            'status' => $validated['status'],
        ]);

        if ($previousStatus !== $validated['status']) {
            NotificationDispatcher::notifyClient(
                client: $topRequest->client,
                eventType: 'top_request_status_updated',
                title: 'Topup Request Updated',
                message: "Your topup request #{$topRequest->id} is {$validated['status']}.",
                meta: [
                    'top_request_id' => $topRequest->id,
                    'ad_account_request_id' => $topRequest->ad_account_request_id,
                    'status' => $validated['status'],
                    'amount' => $topRequest->amount,
                    'currency' => $topRequest->currency,
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Top request updated.',
            'data' => $topRequest->fresh([
                'client:id,name,email',
                'adAccountRequest:id,request_id,business_name,platform,status',
            ]),
        ]);
    }

    public function destroy($id)
    {
        $topRequest = TopRequest::findOrFail($id);
        $topRequest->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Top request deleted.',
        ]);
    }
}
