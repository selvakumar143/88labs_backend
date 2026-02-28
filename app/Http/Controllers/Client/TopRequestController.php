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
        $validated = $request->validate([
            'ad_account_request_id' => [
                'required',
                'integer',
                Rule::exists('ad_account_requests', 'id')->where(function ($query) {
                    $query->where('client_id', Auth::id());
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
        ]);

        $data = TopRequest::create([
            'client_id' => Auth::id(),
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
                'client_id' => Auth::id(),
                'client_name' => Auth::user()->name,
                'ad_account_request_id' => $data->ad_account_request_id,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'status' => $data->status,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Top request submitted.',
            'data' => $data,
        ], 201);
    }

    public function index(Request $request)
    {
        $query = TopRequest::with('adAccountRequest:id,request_id,business_name,platform,status')
            ->where('client_id', Auth::id());

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('ad_account_request_id') && $request->ad_account_request_id !== 'all') {
            $query->where('ad_account_request_id', $request->ad_account_request_id);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->paginate($request->integer('per_page', 10)),
        ]);
    }

    public function myRequests(Request $request)
    {
        return $this->index($request);
    }
}
