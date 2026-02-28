<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdAccountRequestController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'platform' => 'required|string',
            'timezone' => 'required|string',
            'country' => 'required|string',
            'currency' => 'required|string',
            'business_manager_id' => 'required|string',
            'website_url' => 'required|string',
            'account_type' => 'required|string',
            'personal_profile' => 'required|string',
            'number_of_accounts' => 'required|integer|min:1',
        ]);
        $user = Auth::user();

        $lastId = AdAccountRequest::max('id') + 1;
        $requestId = 'REQ-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $data = AdAccountRequest::create([
            'request_id' => $requestId,
            'client_id' => Auth::id(),
            'business_name' => $request->business_name, // 🔥 REQUIRED
            'platform' => $request->platform,
            'timezone' => $request->timezone,
            'market_country' => $request->country,
            'currency' => $request->currency,
            'business_manager_id' => $request->business_manager_id,
            'website_url' => $request->website_url,
            'account_type' => $request->account_type,
            'personal_profile' => $request->personal_profile,
            'additional_notes' => $request->notes,
            'number_of_accounts' => $request->number_of_accounts,
            'status' => AdAccountRequest::STATUS_PENDING,
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'ad_account_request_created',
            title: 'New Ad Account Request',
            message: "{$user->name} submitted ad account request {$data->request_id}.",
            meta: [
                'ad_account_request_id' => $data->id,
                'request_id' => $data->request_id,
                'client_id' => $user->id,
                'client_name' => $user->name,
                'status' => $data->status,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Ad account request submitted.',
            'data' => $data
        ], 201);
    }

    public function index()
    {
        $query = AdAccountRequest::where('client_id', Auth::id());

        if (request()->filled('status') && request()->status !== 'all') {
            $query->where('status', request()->status);
        }

        if (request()->filled('search')) {
            $search = request()->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%")
                    ->orWhere('business_manager_id', 'like', "%{$search}%")
                    ->orWhere('website_url', 'like', "%{$search}%");
            });
        }

        $requests = $query->latest()->paginate(request()->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $requests
        ]);
    }

    public function myRequests()
    {
        return $this->index();
    }
}
