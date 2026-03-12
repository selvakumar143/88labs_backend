<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ServiceController;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::with('user');

        if ($request->filled('search')) {
            $search = $request->string('search')->trim();
            $query->where('clientName', 'like', "%{$search}%");
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower((string) $request->status);

            if (in_array($status, ['active', 'enabled', '1', 'true'], true)) {
                $query->where('enabled', true);
            } elseif (in_array($status, ['inactive', 'disabled', '0', 'false'], true)) {
                $query->where('enabled', false);
            }
        }

        $clients = $query->latest()->paginate($request->integer('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'clientCode' => 'nullable|string|max:255|unique:clients,clientCode',
            'clientName' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'country' => 'required',
            'phone' => 'required',
        ]);

        DB::beginTransaction();

        try {

            // create user WITHOUT password
            $user = User::create([
                'name' => $request->clientName,
                'email' => $request->email,
                'password' => null,
                'email_verified_at' => null,
            ]);

            $user->assignRole('customer');

            $client = Client::create([
                'clientCode' => $request->clientCode,
                'clientName' => $request->clientName,
                'country' => $request->country,
                'email' => $request->email,
                'phone' => $request->phone,
                'clientType' => $request->clientType,
                'niche' => $request->niche,
                'marketCountry' => $request->marketCountry,
                'settlementMode' => $request->settlementMode,
                'statementCycle' => $request->statementCycle,
                'settlementCurrency' => $request->settlementCurrency,
                'cooperationStart' => $request->cooperationStart,
                'serviceFeePercent' => $request->serviceFeePercent,
                'serviceFeeEffectiveTime' => $request->serviceFeeEffectiveTime,
                'enabled' => false,
                'user_id' => $user->id,
            ]);

            if (blank($client->clientCode)) {
                $client->clientCode = 'CL-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT);
                $client->save();
            }

            // Send invite "set password" email with a password broker token
            $token = Password::broker()->createToken($user);
            $url = route('password.setup', [
                'token' => $token,
                'email' => $user->email,
            ]);
            $expireMinutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);
            $subject = 'Set Your Account Password';
            $contentText = implode("\n", [
                'Welcome, '.$user->name.'!',
                'Your account has been created. Set your password to activate your account.',
                'Set Password: '.$url,
                'If the button does not work, copy and paste this link into your browser: '.$url,
                'This link will expire in '.$expireMinutes.' minutes.',
            ]);
            $safeName = htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
            $contentHtml = implode("\n", [
                '<p>Welcome, '.$safeName.'!</p>',
                '<p>Your account has been created. Set your password to activate your account.</p>',
                '<p>
                <a href="'.$safeUrl.'" 
                style="
                display:inline-block;
                padding:12px 22px;
                background-color:#2563eb;
                color:#ffffff;
                text-decoration:none;
                border-radius:6px;
                font-weight:600;
                ">
                Set Password
                </a>
                </p>',
                '<p>If the button does not work, copy and paste this link into your browser:</p>',
                '<p><a href="'.$safeUrl.'">'.$safeUrl.'</a></p>',
                '<p>This link will expire in '.$expireMinutes.' minutes.</p>',
            ]);
            $mailResult = ServiceController::sendMail($user->email, $subject, $contentText, $contentHtml);
            if (isset($mailResult['error'])) {
                throw new \Exception('Mail send failed: '.$mailResult['error']);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Client created. Password setup email sent.',
                'data' => $client->load('user')
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Client $client)
    {
        return response()->json([
            'status' => 'success',
            'data' => $client->load('user'),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'clientCode' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('clients', 'clientCode')->ignore($client->id),
            ],
            'clientName' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($client->user_id),
            ],
            'country' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:255',
            'clientType' => 'sometimes|nullable|string|max:255',
            'niche' => 'sometimes|nullable|string|max:255',
            'marketCountry' => 'sometimes|nullable|string|max:255',
            'settlementMode' => 'sometimes|nullable|string|max:255',
            'statementCycle' => 'sometimes|nullable|string|max:255',
            'settlementCurrency' => 'sometimes|nullable|string|max:255',
            'cooperationStart' => 'sometimes|nullable|date',
            'serviceFeePercent' => 'sometimes|nullable|numeric',
            'serviceFeeEffectiveTime' => 'sometimes|nullable|date',
            'enabled' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();

        try {

            $userData = [];
            if (array_key_exists('clientName', $validated)) {
                $userData['name'] = $validated['clientName'];
            }
            if (array_key_exists('email', $validated)) {
                $userData['email'] = $validated['email'];
            }
            if (!empty($userData)) {
                $client->user->update($userData);
            }

            // Update client data
            $clientFields = [
                'clientCode',
                'clientName',
                'country',
                'email',
                'phone',
                'clientType',
                'niche',
                'marketCountry',
                'settlementMode',
                'statementCycle',
                'settlementCurrency',
                'cooperationStart',
                'serviceFeePercent',
                'serviceFeeEffectiveTime',
                'enabled',
            ];

            $clientData = [];
            foreach ($clientFields as $field) {
                if (array_key_exists($field, $validated)) {
                    $clientData[$field] = $validated[$field];
                }
            }

            if (!empty($clientData)) {
                $client->update($clientData);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Client updated successfully',
                'data' => $client->fresh()->load('user'),
            ]);

        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function setPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|confirmed|min:6',
        ]);
 
        $status = Password::reset(
            $request->only(
                'email', 'password', 'password_confirmation', 'token'
            ),
            function ($user, $password) {

                $user->password = Hash::make($password);
                $user->email_verified_at = now();
                $user->save();

                // activate client
                $user->client->update([
                    'enabled' => true
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {

            return response()->json([
                'status' => 'success',
                'message' => 'Password set successfully. Account activated.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid or expired token'
        ], 400);
    }
    public function destroy(Client $client)
    {
        DB::beginTransaction();

        try {
            $client->user->delete(); // cascade will delete client
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Client deleted successfully',
            ]);

        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
