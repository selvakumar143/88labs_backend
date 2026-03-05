<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminDashboardController extends Controller
{
    public function showLogin()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Admin login route is available.',
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401);
        }

        $user = Auth::user();
        if (!$user || !$user->hasAnyRole(['admin', 'Admin'])) {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Not an Admin.',
            ], 403);
        }

        $request->session()->regenerate();

        return response()->json([
            'status' => 'success',
            'message' => 'Admin login successful',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }

    public function dashboard()
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Admin dashboard route is available.',
        ]);
    }
}
