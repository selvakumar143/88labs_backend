<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;

class UserController extends Controller
{
    public function store(StoreUserRequest $request)
    {
        $payload = $request->validated();

        $user = User::create([
            'name' => $payload['user']['name'],
            'email' => $payload['user']['email'],
            'password' => $payload['user']['password'],
            'email_verified_at' => now(),
        ]);

        $user->assignRole($payload['role']);

        return response()->json([
            'status' => 'success',
            'message' => 'User created successfully',
            'data' => [
                'user' => $user,
                'role' => $user->getRoleNames(),
            ],
        ], 201);
    }
}
