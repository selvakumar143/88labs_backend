<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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

        // Prevent duplicate pending request
        $exists = AdAccountRequest::where('client_id', $user->id)
                    ->where('status', AdAccountRequest::STATUS_PENDING)
                    ->exists();

        if ($exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'You already have a pending request.'
            ], 400);
        }

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
        return response()->json([
            'status' => 'success',
            'message' => 'Ad account request submitted.',
            'data' => $data
        ], 201);
    }

    public function myRequests()
    {
        $requests = AdAccountRequest::where('client_id', Auth::id())
                        ->latest()
                        ->get();

        return response()->json([
            'status' => 'success',
            'data' => $requests
        ]);
    }
}