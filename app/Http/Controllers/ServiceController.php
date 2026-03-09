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
        $clientId = (int) $request->attributes->get('current_client_id');

        // Default services structure
        $defaultServices = [
            'Unbanning Ad Accounts' => false,
            'Unbanning Fan Pages' => false,
            'Unban Business Manager' => false,
            'Purchase Verified Profiles' => false,
            'Meta Competitor Spying' => false,
            'Shopify Spying' => false,
            'Trustpilot Removal' => false,
        ];

        $record = ClientService::where('client_id', $clientId)->first();

        if (!$record) {
            return response()->json([
                'client_id' => $clientId,
                'services' => $defaultServices
            ]);
        }

        // Merge DB values over defaults
        $storedServices = $record->services ?? [];

        $mergedServices = array_merge($defaultServices, $storedServices);

        return response()->json([
            'client_id' => $clientId,
            'services' => $mergedServices
        ]);
    }

    /**
     * Apply / update a single service dynamically
     */
    public function updateService(Request $request)
    {
        $request->validate([
            'service_key' => 'required_without:services|string',
            'status'      => 'required_without:services|boolean',
            'services'    => 'nullable|array',
            'services.*'  => 'boolean',
        ]);

        $clientId   = (int) $request->attributes->get('current_client_id');
        $serviceKey = $request->service_key;
        $status     = $request->status;

        // Default services
        $defaultServices = [
            'Unbanning Ad Accounts' => false,
            'Unbanning Fan Pages' => false,
            'Unban Business Manager' => false,
            'Purchase Verified Profiles' => false,
            'Meta Competitor Spying' => false,
            'Shopify Spying' => false,
            'Trustpilot Removal' => false,
        ];

        $record = ClientService::firstOrCreate(
            ['client_id' => $clientId],
            ['services' => $defaultServices]
        );

        $services = $record->services ?? $defaultServices;

        if ($request->filled('services')) {
            $incomingServices = $request->input('services', []);
            $services = array_merge($services, $incomingServices);
        } else {
            $services[$serviceKey] = $status;
        }

        $record->update([
            'services' => $services
        ]);

        return response()->json([
            'client_id' => $clientId,
            'services'  => $services
        ]);
    }
}
