<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\AdAccountRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdAccountRequestController extends Controller
{
    public function store(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $request->merge([
            'business_name' => $request->input('business_name', $request->input('company_name')),
            'platform' => $request->input('platform', $request->input('ad_platform')),
            'website_url' => $request->input('website_url', $request->input('company_website_url')),
            'personal_profile' => $request->input('personal_profile', $request->input('personal_facebook_profile_link')),
            'number_of_accounts' => $request->input('number_of_accounts', $request->input('number_of_ad_accounts')),
            'notes' => $request->input('notes', $request->input('note')),
            'api' => $request->input('api', AdAccountRequest::API_ENABLE),
        ]);

        $request->validate([
            'req_name' => 'required|string|max:255',
            'business_name' => 'required|string|max:255',
            'platform' => 'required|string',
            'timezone' => 'required|string',
            'country' => 'required|string',
            'currency' => 'required|string',
            'vcc_provider' => 'nullable|string|max:255',
            'business_manager_id' => 'nullable|exists:business_managers,id',
            'website_url' => 'required|string',
            'account_type' => 'required|string',
            'personal_profile' => 'required|string',
            'number_of_accounts' => 'required|integer|min:0',
            'account_preference' => 'nullable|string|max:255',
            'api' => 'sometimes|string|in:enable,disable',
            'type' => 'prohibited',
            'master_id' => 'prohibited',
            'account_name' => 'prohibited',
            'account_id' => 'prohibited',
            'card_type' => 'prohibited',
            'card_number' => 'prohibited',
        ]);
        $user = Auth::user();

        $baseData = [
            'client_id' => $clientId,
            'sub_user_id' => Auth::id(),
            'req_name' => $request->req_name,
            'api' => $request->api ?: AdAccountRequest::API_ENABLE,
            'business_name' => $request->business_name, // 🔥 REQUIRED
            'platform' => $request->platform,
            'timezone' => $request->timezone,
            'market_country' => $request->country,
            'currency' => $request->currency,
            'vcc_provider' => $request->vcc_provider,
            'business_manager_id' => $request->business_manager_id,
            'account_preference' => $request->account_preference,
            'website_url' => $request->website_url,
            'account_type' => $request->account_type,
            'personal_profile' => $request->personal_profile,
            'additional_notes' => $request->notes,
            'number_of_accounts' => $request->number_of_accounts,
            'status' => AdAccountRequest::STATUS_PENDING,
        ];

        $data = DB::transaction(function () use ($baseData, $request) {
            $master = AdAccountRequest::create(array_merge($baseData, [
                'request_id' => $this->generateRequestId(),
                'type' => AdAccountRequest::TYPE_MASTER,
                'master_id' => null,
            ]));

            for ($childIndex = 0; $childIndex < (int) $request->number_of_accounts; $childIndex++) {
                AdAccountRequest::create(array_merge($baseData, [
                    'request_id' => $this->generateRequestId(),
                    'type' => AdAccountRequest::TYPE_CHILD,
                    'master_id' => $master->id,
                ]));
            }

            return $master;
        });

        NotificationDispatcher::notifyAdmins(
            eventType: 'ad_account_request_created',
            title: 'New Ad Account Request',
            message: "{$user->name} submitted ad account request {$data->request_id}.",
            meta: [
                'ad_account_request_id' => $data->id,
                'request_id' => $data->request_id,
                'client_id' => $clientId,
                'client_name' => $user->name,
                'status' => $data->status,
            ]
        );

        $data->load([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'businessManager',
        ]);
        $data->client_name = $this->resolveClientName($data);
        $data->sub_user_id = $data->sub_user_id;
        $data->created_by = optional($data->creatorUser)->name
            ?? optional(optional($data->client)->primaryAdmin)->name
            ?? $data->client_name;
        $data->business_manager_name = optional($data->businessManager)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Ad account request submitted.',
            'data' => $data
        ], 201);
    }

    public function index()
    {
        $clientId = (int) request()->attributes->get('current_client_id');

        $query = AdAccountRequest::with([
                'client.primaryAdmin:id,name',
                'creatorUser:id,name',
                'businessManager',
            ])
            ->where('client_id', $clientId);

        if (request()->filled('status') && request()->status !== 'all') {
            $query->where('status', request()->status);
        }

        if (request()->filled('search')) {
            $search = request()->search;
            $query->where(function ($q) use ($search) {
                $q->where('req_name', 'like', "%{$search}%")
                    ->orWhere('request_id', 'like', "%{$search}%")
                    ->orWhere('business_name', 'like', "%{$search}%")
                    ->orWhere('platform', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('api', 'like', "%{$search}%")
                    ->orWhere('business_manager_id', 'like', "%{$search}%")
                    ->orWhere('website_url', 'like', "%{$search}%");
            });
        }

        $requests = $query->latest()->paginate(request()->integer('per_page', 10));
        $requests->getCollection()->transform(function ($item) {
            $item->client_name = $this->resolveClientName($item);
            $item->sub_user_id = $item->sub_user_id;
            $item->created_by = optional($item->creatorUser)->name
                ?? optional(optional($item->client)->primaryAdmin)->name
                ?? $item->client_name;
            $item->business_manager_name = optional($item->businessManager)->name;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $requests
        ]);
    }

    public function myRequests()
    {
        return $this->index();
    }

    private function resolveClientName(AdAccountRequest $request): ?string
    {
        return data_get($request, 'client.clientName')
            ?? data_get($request, 'client.client_name')
            ?? data_get($request, 'client.name');
    }

    private function generateRequestId(): string
    {
        $nextId = ((int) AdAccountRequest::max('id')) + 1;

        return 'REQ-' . str_pad((string) $nextId, 4, '0', STR_PAD_LEFT);
    }
}
