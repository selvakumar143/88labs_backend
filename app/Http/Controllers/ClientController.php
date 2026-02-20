<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index()
    {
        $clients = Client::with('user')->get();
        return view('clients.index', compact('clients'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id' => 'required|unique:clients,id',
            'clientName' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'country' => 'required',
            'phone' => 'required',
        ]);

        DB::beginTransaction();

        try {

            // 1️⃣ Create user (AUTO id, no overwrite)
            $user = User::create([
                'name' => $request->clientName,
                'email' => $request->email,
                'password' => Hash::make('123456'), // temporary password
                'email_verified_at' => now(),
            ]);

            $user->assignRole('Customer');

            // 2️⃣ Create client linked to that user
            Client::create([
                'id' => $request->id,
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

            DB::commit();

            return redirect()->route('clients.index')
                ->with('success', 'Client created successfully');

        } catch (\Exception $e) {

            DB::rollBack();
            return back()->withErrors('Something went wrong.');
        }
    }

    public function update(Request $request, Client $client)
    {
        $request->validate([
            'clientName' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $client->user_id,
        ]);

        DB::beginTransaction();

        try {

            // Update linked user safely
            $client->user->update([
                'name' => $request->clientName,
                'email' => $request->email,
            ]);

            // Update client data
            $client->update([
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
            ]);

            DB::commit();

            return redirect()->route('clients.index')
                ->with('success', 'Client updated successfully');

        } catch (\Exception $e) {

            DB::rollBack();
            return back()->withErrors('Something went wrong.');
        }
    }

    public function destroy(Client $client)
    {
        DB::beginTransaction();

        try {
            $client->user->delete(); // cascade will delete client
            DB::commit();

            return redirect()->route('clients.index')
                ->with('success', 'Client deleted successfully');

        } catch (\Exception $e) {

            DB::rollBack();
            return back()->withErrors('Something went wrong.');
        }
    }
}
