<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountManagement;
use Illuminate\Http\Request;

class AccountManagementController extends Controller
{
    /**
     * List all accounts.
     * GET /api/admin/account-management
     */
    public function index(Request $request)
    {
        $accounts = AccountManagement::with(['client', 'businessManager'])
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $accounts,
        ]);
    }

    /**
     * Create a new account.
     * POST /api/admin/account-management
     */
    public function store(Request $request)
    {
        $request->validate([
            'client_id' => 'required|exists:clients,id',
            'business_manager_id' => 'nullable|exists:business_managers,id',
            'name' => 'required|string|max:255',
            'account_id' => 'required|string|unique:account_management,account_id',
            'platform' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'account_created_at' => 'required|date',
            'status' => 'sometimes|string|in:pending,active,inactive',
        ]);

        $account = AccountManagement::create([
            'client_id' => $request->client_id,
            'business_manager_id' => $request->business_manager_id,
            'name' => $request->name,
            'account_id' => $request->account_id,
            'platform' => $request->platform,
            'currency' => $request->currency,
            'account_created_at' => $request->account_created_at,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Account created successfully.',
            'data' => $account->load(['client', 'businessManager']),
        ], 201);
    }
}
