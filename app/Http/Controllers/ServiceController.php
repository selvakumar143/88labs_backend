<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientService;
use App\Services\SendGridService;



class ServiceController extends Controller
{
    public static function sendMail(string $to, string $subject, string $contentText, ?string $contentHtml = null): array
    {
        return SendGridService::sendMail($to, $subject, $contentText, $contentHtml);
    }

    /**
     * Get services for a client
     */
    public function getServices(Request $request)
    {
        $request->validate([
            'client_id' => 'required|integer',
        ]);

        $clientId = (int) $request->input('client_id');

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
            'client_id'   => 'required|integer',
            'service_key' => 'required_without:services|string',
            'status'      => 'required_without:services|boolean',
            'services'    => 'nullable|array',
            'services.*'  => 'boolean',
        ]);

        $clientId   = $request->client_id;
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
    public function sendTestMail()
    {
        $result = self::sendMail(
            // "sp.selvakumar2012@gmail.com",
            "siva.techyazh@gmail.com",
            "Test Email",
            "Hello from SendGrid API",
            "<p>Hello from SendGrid API</p>"
        );

        return response()->json($result);
    }
}
