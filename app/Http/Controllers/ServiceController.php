<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientService;

class ServiceController extends Controller
{
    /**
     * Get services for a client
     */
    public function getServices(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
            'services' => 'required|array',
        ]);

        $clientId = $request->client_id;
        $services = $request->services;

        $record = ClientService::updateOrCreate(
            ['client_id' => $clientId],
            ['services' => $services]
        );

        return response()->json([
            'client_id' => $clientId,
            'services' => $record->services
        ]);
    }

    /**
     * Apply / update a single service dynamically
     */
    public function updateService(Request $request)
    {
        $request->validate([
            'client_id'   => 'required|integer',
            'service_key' => 'required|string',
            'status'      => 'required|boolean'
        ]);

        $clientId   = $request->client_id;
        $serviceKey = $request->service_key;
        $status     = $request->status;

        $record = ClientService::where('client_id', $clientId)->first();

        if (!$record) {
            return response()->json([
                'error' => 'Services not initialized for this client.'
            ], 400);
        }

        $services = $record->services ?? [];

        $services[$serviceKey] = $status;

        $record->update([
            'services' => $services
        ]);

        return response()->json([
            'client_id' => $clientId,
            'services'  => $services
        ]);
    }
}
