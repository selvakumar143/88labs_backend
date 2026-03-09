<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;

class TeamUserController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $clientId = (int) $request->attributes->get('current_client_id');

        $isPrimaryAdmin = (int) $user->id === (int) $request->attributes->get('current_client_owner_user_id');
        if (!$user->hasAnyRole(['client_admin']) && !$isPrimaryAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only client admins can view team users.',
            ], 403);
        }

        $query = User::query()
            ->select('id', 'client_id', 'name', 'email', 'status', 'created_at')
            ->with('roles:id,name')
            ->where('client_id', $clientId);

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $teamUsers = $query
            ->latest()
            ->paginate($request->integer('per_page', 10));

        $teamUsers->getCollection()->transform(function (User $member) {
            $member->roles = $member->roles->pluck('name')->values();
            return $member;
        });

        return response()->json([
            'status' => 'success',
            'data' => $teamUsers,
        ]);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        $clientId = (int) $request->attributes->get('current_client_id');

        $isPrimaryAdmin = (int) $admin->id === (int) $request->attributes->get('current_client_owner_user_id');
        if (!$admin->hasAnyRole(['client_admin']) && !$isPrimaryAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only client admins can create team users.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'string', Rule::in(['client_admin', 'client_manager', 'client_viewer'])],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'send_invite' => ['sometimes', 'boolean'],
        ]);

        DB::beginTransaction();

        try {
            $member = User::create([
                'client_id' => $clientId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

            // Keep backward compatibility with existing customer middleware checks.
            $member->assignRole('customer');
            $member->assignRole($validated['role']);

            $shouldSendInvite = (bool) ($validated['send_invite'] ?? empty($validated['password']));
            if ($shouldSendInvite) {
                $token = Password::broker()->createToken($member);
                $member->notify(new SetPasswordNotification($token));
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Team user created successfully.',
                'data' => [
                    'user' => $member->load('roles:id,name'),
                    'roles' => $member->getRoleNames()->values(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create team user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
