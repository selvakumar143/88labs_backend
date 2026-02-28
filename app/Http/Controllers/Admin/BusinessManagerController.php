<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessManager;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BusinessManagerController extends Controller
{
    public function index(Request $request)
    {
        $query = BusinessManager::query();

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mail', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', strtolower((string) $request->status));
        }

        $businessManagers = $query->latest()->paginate($request->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $businessManagers,
        ]);
    }

    public function show(BusinessManager $businessManager)
    {
        return response()->json([
            'status' => 'success',
            'data' => $businessManager,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mail' => ['required', 'email', 'max:255', 'unique:business_managers,mail'],
            'contact' => ['required', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $businessManager = BusinessManager::create([
            ...$validated,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Business manager created successfully.',
            'data' => $businessManager,
        ], 201);
    }

    public function update(Request $request, BusinessManager $businessManager)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'mail' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('business_managers', 'mail')->ignore($businessManager->id),
            ],
            'contact' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);

        $businessManager->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Business manager updated successfully.',
            'data' => $businessManager->fresh(),
        ]);
    }

    public function destroy(BusinessManager $businessManager)
    {
        $businessManager->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Business manager deleted successfully.',
        ]);
    }
}
