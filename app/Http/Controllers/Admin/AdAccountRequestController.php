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
        $query = AdAccountRequest::with(['client.client']);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $clientId = $request->input('client_id', $request->input('client'));
        if (!empty($clientId) && $clientId !== 'all') {
            $query->where('client_id', $clientId);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('request_id', 'like', "%{$request->search}%")
                  ->orWhereHas('client', function ($sub) use ($request) {
                      $sub->where('name', 'like', "%{$request->search}%");
                  });
            });
        }

        $data = $query->latest()->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = optional(optional($item->client)->client)->clientName;
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

        return response()->json([
            'status' => 'success',
            'message' => 'Request status updated.',
            'data' => $requestData,
        ]);
    }
}
