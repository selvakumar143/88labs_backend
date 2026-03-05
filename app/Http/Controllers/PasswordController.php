<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordController extends Controller
{
    public function resetFromLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->password = Hash::make($password);
                $user->email_verified_at = $user->email_verified_at ?? now();
                $user->setRememberToken(Str::random(60));
                $user->save();

                if ($user->relationLoaded('client')) {
                    $client = $user->client;
                } else {
                    $client = $user->client()->first();
                }

                if ($client && !$client->enabled) {
                    $client->update(['enabled' => true]);
                }

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successfully.',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
        ], 400);
    }
}
