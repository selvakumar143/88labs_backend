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
        $request->validate([
            'clientCode' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('clients', 'clientCode')->ignore($client->id),
            ],
            'clientName' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($client->user_id),
            ],
            'country' => 'required',
            'phone' => 'required',
        ]);

        DB::beginTransaction();

        try {

            // Update linked user safely
            $client->user->update([
                'name' => $request->clientName,
                'email' => $request->email,
            ]);

            // Update client data
            $clientData = [
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
            ];

            if ($request->filled('clientCode')) {
                $clientData['clientCode'] = $request->clientCode;
            }

            $client->update($clientData);

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
