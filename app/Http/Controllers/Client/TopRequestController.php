<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\TopRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TopRequestController extends Controller
{
    public function store(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $validated = $request->validate([
            'ad_account_request_id' => [
                'required',
                'integer',
                Rule::exists('ad_account_requests', 'id')->where(function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
        ]);

        $data = TopRequest::create([
            'client_id' => $clientId,
            'sub_user_id' => Auth::id(),
            'ad_account_request_id' => $validated['ad_account_request_id'],
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'status' => TopRequest::STATUS_PENDING,
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'top_request_created',
            title: 'New Topup Request',
            message: Auth::user()->name . " submitted topup request #{$data->id}.",
            meta: [
                'top_request_id' => $data->id,
                'client_id' => $clientId,
                'client_name' => Auth::user()->name,
                'ad_account_request_id' => $data->ad_account_request_id,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'status' => $data->status,
            ]
        );

        $data->load([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'adAccountRequest:id,request_id,business_name,platform,status',
        ]);
        $data->client_name = $this->resolveClientName($data);
        $data->created_by = optional($data->creatorUser)->name
            ?? optional(optional($data->client)->primaryAdmin)->name
            ?? $data->client_name;

        return response()->json([
            'status' => 'success',
            'message' => 'Top request submitted.',
            'data' => $data,
        ], 201);
    }

    public function index(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $query = TopRequest::with([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'adAccountRequest:id,request_id,business_name,platform,status',
        ])
            ->where('client_id', $clientId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('ad_account_request_id') && $request->ad_account_request_id !== 'all') {
            $query->where('ad_account_request_id', $request->ad_account_request_id);
        }

        $data = $query->orderByDesc('id')->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = $this->resolveClientName($item);
            $item->created_by = optional($item->creatorUser)->name
                ?? optional(optional($item->client)->primaryAdmin)->name
                ?? $item->client_name;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function myRequests(Request $request)
    {
        return $this->index($request);
    }

    private function resolveClientName(TopRequest $request): ?string
    {
        return data_get($request, 'client.clientName')
            ?? data_get($request, 'client.client_name')
            ?? data_get($request, 'client.name');
    }
}
