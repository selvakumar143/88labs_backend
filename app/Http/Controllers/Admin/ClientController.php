<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::with('user')->get();
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
            'password' => 'required|string|min:6',
            'country' => 'required',
            'phone' => 'required',
        ]);

        DB::beginTransaction();

        try {

            // 1. Create user (auto id)
            $user = User::create([
                'name' => $request->clientName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(),
            ]);

            $user->assignRole('customer');

            // 2. Create client linked to that user
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
                'enabled' => true, // default
                'user_id' => $user->id, // AUTO GENERATED ID
            ]);

            if (blank($client->clientCode)) {
                $client->clientCode = 'CL-'.str_pad((string) $client->id, 4, '0', STR_PAD_LEFT);
                $client->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Client created successfully',
                'data' => $client->load('user'),
            ], 201);

        } catch (\Exception $e) {

            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
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
