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
        $accounts = AccountManagement::with('client')->latest()->paginate(10);

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
            'name' => 'required|string|max:255',
            'account_id' => 'required|string|unique:account_management,account_id',
            'client_name' => 'required|string|max:255',
            'platform' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'account_created_at' => 'required|date',
            'status' => 'sometimes|string|in:pending,active,inactive',
        ]);

        $account = AccountManagement::create([
            'client_id' => $request->client_id,
            'name' => $request->name,
            'account_id' => $request->account_id,
            'client_name' => $request->client_name,
            'platform' => $request->platform,
            'currency' => $request->currency,
            'account_created_at' => $request->account_created_at,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Account created successfully.',
            'data' => $account,
        ], 201);
    }
}