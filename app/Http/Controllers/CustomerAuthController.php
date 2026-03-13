<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use App\Models\User;

class CustomerAuthController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | REGISTER
    |--------------------------------------------------------------------------
    */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('customer');

        $token = $user->createToken('customer_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Registration successful',
            'user' => $user,
            'role' => $user->getRoleNames(),
            'token' => $token,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGIN
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

        if (!$user->hasAnyRole(['client_admin', 'client_manager', 'client_viewer'])) {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Not a client user.'
            ], 403);
        }

        if ($user->status !== 'active') {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Your account is inactive.',
            ], 403);
        }

        $tenantClient = $user->tenantClient()->with('primaryAdmin:id,status')->first() ?? $user->client()->with('primaryAdmin:id,status')->first();

        if ($tenantClient && !$tenantClient->enabled) {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Your client account is disabled.',
            ], 403);
        }

        if ($tenantClient && $tenantClient->primaryAdmin && $tenantClient->primaryAdmin->status !== 'active') {
            Auth::logout();

            return response()->json([
                'status' => 'error',
                'message' => 'Your client admin account is inactive.',
            ], 403);
        }

        $token = $user->createToken('customer_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'role' => $user->getRoleNames(),
            'token' => $token,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | LOGOUT
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
    | UPDATE PROFILE (Name + Password)
    |--------------------------------------------------------------------------
    */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'current_password' => 'required_with:password|string',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($request->filled('name')) {
            $user->name = $request->name;
        }

        if ($request->filled('password')) {

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->password = Hash::make($request->password);
        }

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Profile updated successfully',
            'user' => $user,
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
        If only email is provided → send reset link
        If email + token + password → reset password
        */

        // CASE 1: SEND RESET LINK
        if ($request->has('email') && !$request->has('token')) {

            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            $user = User::where('email', $request->email)->first();
            if (!$user || !$user->hasAnyRole(['client_admin', 'client_manager', 'client_viewer'])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized. Not a client user.',
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

        // CASE 2: RESET PASSWORD
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
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
