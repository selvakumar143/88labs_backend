<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
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
            $creatorAdminId = optional($request->user())->id;

            // create user WITHOUT password
            $user = User::create([
                'name' => $request->clientName,
                'email' => $request->email,
                'password' => null,
                'email_verified_at' => null,
            ]);

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
                'admin_created_by' => $creatorAdminId,
                'primary_admin_user_id' => $user->id,
            ]);

            $user->update([
                'client_id' => $client->id,
                'created_by' => $client->id,
            ]);

            $user->assignRole('client_admin');

            if (blank($client->clientCode)) {
                $client->clientCode = 'CL-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT);
                $client->save();
            }

            // Send invite "set password" email with a password broker token
            $token = Password::broker()->createToken($user);
            $user->notify(new SetPasswordNotification($token));

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
                Rule::unique('users', 'email')->ignore($client->primary_admin_user_id),
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

                // Activate tenant only when this user is the primary client owner.
                $ownedClient = $user->client()->first();
                if ($ownedClient && !$ownedClient->enabled) {
                    $ownedClient->update([
                        'enabled' => true
                    ]);
                }
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
