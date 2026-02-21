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

        return response()->json([
            'status' => 'success',
            'message' => 'Request status updated.',
            'data' => $requestData,
        ]);
    }
}
