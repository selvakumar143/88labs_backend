<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClientService;
use App\Services\SendGridService;



class ServiceController extends Controller
{
    //private const DEFAULT_LOGO_SRC = 'data:image/png;base64,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';
    
    public static function sendMail(
        string $to,
        string $subject,
        string $contentText,
        ?string $contentHtml = null,
        array $templateData = []
    ): array
    {
        return SendGridService::sendMail($to, $subject, $contentText, $contentHtml, $templateData);
    }

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
    public function sendTestMail()
    {
        $result = self::sendMail(
            "siva.techyazh@gmail.com",
            "Test Email",
            "Hello from 88Labs.\nThis email was rendered using the Laravel mail template.",
            null,
            [
                'heading' => 'Template Test Email',
                'greeting' => 'Hello,',
                'footer_lines' => [
                    'This is a test email generated from the Laravel Blade mail template.',
                ],
            ]
        );

        return response()->json($result);
    }

    public function sendDynamicMail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:255',
            'mail_type' => 'nullable|in:reset_password,set_password,custom',
            'message' => 'nullable|string',
            'greeting' => 'nullable|string|max:255',
            'heading' => 'nullable|string|max:255',
            'action_text' => 'nullable|string|max:255',
            'action_url' => 'nullable|string|max:2000',
            'lines' => 'nullable|array',
            'lines.*' => 'nullable|string',
            'footer_lines' => 'nullable|array',
            'footer_lines.*' => 'nullable|string',
        ]);

        $mailType = $validated['mail_type'] ?? 'custom';
        $templateData = $this->buildTemplateData($validated, $mailType);
        $contentText = $this->buildContentText($templateData);

        $result = self::sendMail(
            $validated['email'],
            $validated['subject'],
            $contentText,
            null,
            $templateData
        );

        $statusCode = isset($result['error']) ? 500 : 200;

        return response()->json($result, $statusCode);
    }

    private function buildTemplateData(array $validated, string $mailType): array
    {
        $preset = match ($mailType) {
            'reset_password' => [
                'heading' => 'Reset Your Password',
                'greeting' => 'Hello,',
                'lines' => [
                    'You are receiving this email because we received a password reset request for your account.',
                ],
                'action_text' => 'Reset Password',
                'footer_lines' => [
                    'This password reset link will expire in 60 minutes.',
                    'If you did not request a password reset, no further action is required.',
                ],
            ],
            'set_password' => [
                'heading' => 'Set Your Account Password',
                'greeting' => 'Welcome,',
                'lines' => [
                    'Your account has been created. Set your password to activate your account.',
                ],
                'action_text' => 'Set Password',
                'footer_lines' => [
                    'This link will expire in 60 minutes.',
                ],
            ],
            default => [
                'heading' => $validated['heading'] ?? $validated['subject'],
                'greeting' => $validated['greeting'] ?? null,
                'lines' => $validated['lines'] ?? $this->linesFromMessage($validated['message'] ?? ''),
                'action_text' => $validated['action_text'] ?? null,
                'footer_lines' => $validated['footer_lines'] ?? [],
            ],
        };

        return [
            'logo_src' => $this->defaultLogoSrc(),
            'heading' => $validated['heading'] ?? $preset['heading'],
            'greeting' => $validated['greeting'] ?? $preset['greeting'],
            'lines' => $validated['lines'] ?? $preset['lines'],
            'action_text' => $validated['action_text'] ?? $preset['action_text'],
            'action_url' => $validated['action_url'] ?? null,
            'footer_lines' => $validated['footer_lines'] ?? $preset['footer_lines'],
        ];
    }

    // private function defaultLogoSrc(): string
    // {
    //     return 'https://backend.88labs-agency.com/images/logo.png?v=2';
    // }

    private function defaultLogoSrc(): string
{
    return 'https://backend.88labs-agency.com/images/logo.png?v=' . time();
}

    private function buildContentText(array $templateData): string
    {
        $parts = [];

        if (!empty($templateData['greeting'])) {
            $parts[] = $templateData['greeting'];
        }

        $parts = array_merge($parts, $templateData['lines'] ?? []);

        if (!empty($templateData['action_text'])) {
            $parts[] = $templateData['action_text'];
        }

        if (!empty($templateData['action_url'])) {
            $parts[] = 'If the button does not work, copy and paste this link into your browser:';
            $parts[] = $templateData['action_url'];
        }

        return implode("\n\n", array_merge($parts, $templateData['footer_lines'] ?? []));
    }

    private function linesFromMessage(string $message): array
    {
        $parts = preg_split('/\r\n|\r|\n/', $message) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn ($line) => $line !== ''));
    }
}
