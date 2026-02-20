<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdAccountRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = AdAccountRequest::with('client');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('request_id', 'like', "%{$request->search}%")
                  ->orWhereHas('client', function ($sub) use ($request) {
                      $sub->where('name', 'like', "%{$request->search}%");
                  });
            });
        }

        $data = $query->latest()->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function approve($id)
    {
        $requestData = AdAccountRequest::findOrFail($id);

        if ($requestData->status !== AdAccountRequest::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request already processed.'
            ], 400);
        }

        $requestData->update([
            'status' => AdAccountRequest::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Request approved.'
        ]);
    }

    public function reject($id)
    {
        $requestData = AdAccountRequest::findOrFail($id);

        if ($requestData->status !== AdAccountRequest::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Request already processed.'
            ], 400);
        }

        $requestData->update([
            'status' => AdAccountRequest::STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Request rejected.'
        ]);
    }
}