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
    private const DEFAULT_TEAM_ROLE = 'client_manager';
    private const ALLOWED_TEAM_ROLES = ['client_admin', 'client_manager', 'client_viewer'];

    public function index(Request $request)
    {
        $user = $request->user();
        $clientId = (int) $request->attributes->get('current_client_id');

        if ($response = $this->authorizeClientAdmin($request, 'view team users')) {
            return $response;
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

        $formattedUsers = $teamUsers->getCollection()
            ->map(fn (User $member) => $this->formatTeamUserForList($member))
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => $formattedUsers,
            'pagination' => [
                'current_page' => $teamUsers->currentPage(),
                'per_page' => $teamUsers->perPage(),
                'total' => $teamUsers->total(),
                'last_page' => $teamUsers->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        $clientId = (int) $request->attributes->get('current_client_id');

        $request->merge([
            'name' => $request->input('name', $request->input('username')),
            'password_confirmation' => $request->input('password_confirmation', $request->input('confirmation_password')),
            'role' => $request->input('role', self::DEFAULT_TEAM_ROLE),
        ]);

        if ($response = $this->authorizeClientAdmin($request, 'create team users')) {
            return $response;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['required', 'string', Rule::in(self::ALLOWED_TEAM_ROLES)],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'send_invite' => ['sometimes', 'boolean'],
        ]);

        DB::beginTransaction();

        try {
            $member = User::create([
                'client_id' => $clientId,
                'created_by' => $clientId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'] ?? null,
                'status' => $validated['status'] ?? 'active',
            ]);

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

    public function update(Request $request, User $user)
    {
        if ($response = $this->authorizeClientAdmin($request, 'update team users')) {
            return $response;
        }

        if ($response = $this->validateManagedUser($request, $user)) {
            return $response;
        }

        $request->merge([
            'name' => $request->input('name', $request->input('username')),
            'password_confirmation' => $request->input('password_confirmation', $request->input('confirmation_password')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'role' => ['sometimes', 'required', 'string', Rule::in(self::ALLOWED_TEAM_ROLES)],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        DB::beginTransaction();

        try {
            $user->fill([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'status' => $validated['status'],
            ]);

            if (!empty($validated['password'])) {
                $user->password = $validated['password'];
            }

            $user->save();

            if (!empty($validated['role'])) {
                $user->syncRoles([$validated['role']]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Team user updated successfully.',
                'data' => [
                    'user' => $user->load('roles:id,name'),
                    'roles' => $user->getRoleNames()->values(),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update team user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, User $user)
    {
        if ($response = $this->authorizeClientAdmin($request, 'delete team users')) {
            return $response;
        }

        if ($response = $this->validateManagedUser($request, $user, true)) {
            return $response;
        }

        DB::beginTransaction();

        try {
            $user->syncRoles([]);
            $user->tokens()->delete();
            $user->delete();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Team user deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete team user.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function authorizeClientAdmin(Request $request, string $action)
    {
        $admin = $request->user();
        $isPrimaryAdmin = (int) $admin->id === (int) $request->attributes->get('current_client_owner_user_id');

        if (!$admin->hasAnyRole(['client_admin']) && !$isPrimaryAdmin) {
            return response()->json([
                'status' => 'error',
                'message' => "Only client admins can {$action}.",
            ], 403);
        }

        return null;
    }

    private function validateManagedUser(Request $request, User $user, bool $forDelete = false)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $currentUser = $request->user();
        $ownerUserId = (int) $request->attributes->get('current_client_owner_user_id');

        if ((int) $user->client_id !== $clientId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team user not found for this client.',
            ], 404);
        }

        if ((int) $user->id === $ownerUserId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Primary client admin cannot be modified here.',
            ], 422);
        }

        if ($forDelete && (int) $user->id === (int) $currentUser->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        if (!$user->hasAnyRole(self::ALLOWED_TEAM_ROLES)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only client team users can be managed here.',
            ], 422);
        }

        return null;
    }

    private function formatTeamUserForList(User $member): array
    {
        return [
            'client_id' => $member->client_id,
            'name' => $member->name,
            'email' => $member->email,
            'status' => $member->status,
            'created_at' => $member->created_at,
            'roles' => $member->roles->pluck('name')->first(),
        ];
    }
}
