<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountManagement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountManagementController extends Controller
{
    /**
     * List all accounts.
     * GET /api/admin/account-management
     */
    public function index(Request $request)
    {
        $accounts = AccountManagement::with(['businessManager'])
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
            'business_manager_id' => 'nullable|exists:business_managers,id',
            'name' => 'required|string|max:255',
            'account_id' => 'required|string|unique:account_management,account_id',
            'card_type' => 'nullable|string|max:100',
            'card_number' => 'nullable|string|max:50',
            'platform' => 'required|string|max:255',
            'currency' => 'required|string|max:10',
            'account_created_at' => 'required|date',
            'status' => 'sometimes|string|in:pending,active,inactive',
        ]);

        $account = AccountManagement::create([
            'business_manager_id' => $request->business_manager_id,
            'name' => $request->name,
            'account_id' => $request->account_id,
            'card_type' => $request->card_type,
            'card_number' => $request->card_number,
            'platform' => $request->platform,
            'currency' => $request->currency,
            'account_created_at' => $request->account_created_at,
            'status' => $request->status ?? 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Account created successfully.',
            'data' => $account->load(['businessManager']),
        ], 201);
    }

    /**
     * Update account.
     * PUT /api/admin/account-management/{id}
     */
    public function update(Request $request, $id)
    {
        $account = AccountManagement::findOrFail($id);

        $validated = $request->validate([
            'business_manager_id' => 'sometimes|nullable|exists:business_managers,id',
            'name' => 'sometimes|string|max:255',
            'account_id' => [
                'sometimes',
                'string',
                Rule::unique('account_management', 'account_id')->ignore($account->id),
            ],
            'card_type' => 'sometimes|nullable|string|max:100',
            'card_number' => 'sometimes|nullable|string|max:50',
            'platform' => 'sometimes|string|max:255',
            'currency' => 'sometimes|string|max:10',
            'account_created_at' => 'sometimes|date',
            'status' => 'sometimes|string|in:pending,active,inactive',
        ]);

        $account->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Account updated successfully.',
            'data' => $account->fresh()->load(['businessManager']),
        ]);
    }
}
