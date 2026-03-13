<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use App\Models\User;

class AdminAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ADMIN LOGIN
    |--------------------------------------------------------------------------
    */

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials'
            ], 401);
        }

        $user = Auth::user();

        if (!$user->hasAnyRole(['admin', 'Admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Not an Admin.'
            ], 403);
        }

        $token = $user->createToken('admin_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Admin login successful',
            'user' => $user,
            'role' => $user->getRoleNames(),
            'token' => $token,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN LOGOUT
    |--------------------------------------------------------------------------
    */

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]);
    }

/*
|--------------------------------------------------------------------------
| FORGOT + RESET PASSWORD (SINGLE FUNCTION)
|--------------------------------------------------------------------------
*/

public function passwordHandler(Request $request)
{
    /*
        CASE 1 → Only email → Send reset link
        CASE 2 → Email + token + password → Reset password
    */

    // ================================
    // CASE 1: SEND RESET LINK
    // ================================
    if ($request->has('email') && !$request->has('token')) {

        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user || !$user->hasAnyRole(['admin', 'Admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Not an Admin.',
            ], 403);
        }

        $token = Password::broker()->createToken($user);
        $url = route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ]);
        $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
        $subject = 'Reset Password Notification';
        $contentText = implode("\n", [
            'You are receiving this email because we received a password reset request for your account.',
            'Reset Password: '.$url,
            'If the button does not work, copy and paste this link into your browser: '.$url,
            'This password reset link will expire in '.$expireMinutes.' minutes.',
            'If you did not request a password reset, no further action is required.',
        ]);
        $mailResult = ServiceController::sendMail($user->email, $subject, $contentText, null, [
            'heading' => 'Reset Your Password',
            'greeting' => 'Hello '.$user->name.',',
            'lines' => [
                'You are receiving this email because we received a password reset request for your account.',
            ],
            'action_text' => 'Reset Password',
            'action_url' => $url,
            'footer_lines' => [
                'This password reset link will expire in '.$expireMinutes.' minutes.',
                'If you did not request a password reset, no further action is required.',
            ],
        ]);
        if (isset($mailResult['error'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send password reset email',
                'error' => $mailResult['error'],
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Password reset link sent to your email'
        ]);
    }

    // ================================
    // CASE 2: RESET PASSWORD
    // ================================
    $request->validate([
        'email' => 'required|email',
        'token' => 'required|string',
        'password' => 'required|string|min:6|confirmed',
    ]);

    $status = Password::reset(
        $request->only('email', 'password', 'password_confirmation', 'token'),
        function ($user, $password) {

            // Ensure only admin can reset here
            if (!$user->hasAnyRole(['admin', 'Admin'])) {
                return false;
            }

            $user->password = Hash::make($password);
            $user->setRememberToken(Str::random(60));
            $user->save();

            event(new PasswordReset($user));
        }
    );

    if ($status === Password::PASSWORD_RESET) {
        return response()->json([
            'status' => 'success',
            'message' => 'Password reset successfully'
        ]);
    }

    return response()->json([
        'status' => 'error',
        'message' => __($status)
    ], 400);
}
}
