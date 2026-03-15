<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GetSpendData;
use Illuminate\Http\Request;

class SpendDataController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'nullable',
            'account_id' => 'nullable|string',
            'date_start' => 'nullable|date',
            'date_end' => 'nullable|date|after_or_equal:date_start',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = GetSpendData::query();

        if (!empty($validated['client_id']) && $validated['client_id'] !== 'all') {
            $query->where('client_id', $validated['client_id']);
        }

        if (!empty($validated['account_id']) && $validated['account_id'] !== 'all') {
            $query->where('account_id', $validated['account_id']);
        }

        if (!empty($validated['date_start'])) {
            $query->whereDate('date_start', '>=', $validated['date_start']);
        }

        if (!empty($validated['date_end'])) {
            $query->whereDate('date_stop', '<=', $validated['date_end']);
        }

        $perPage = (int) ($validated['per_page'] ?? 10);

        $items = $query
            ->orderByDesc('date_start')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $items,
        ]);
    }
}
