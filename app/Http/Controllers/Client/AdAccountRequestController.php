<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CsvExcelResponse;
use App\Models\AdAccountRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdAccountRequestController extends Controller
{
    use CsvExcelResponse;

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
            'number_of_accounts' => 'required|integer|min:1|max:50',
            'bm_id' => 'nullable|string|max:255',
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
            'api' => $request->api ?: AdAccountRequest::API_ENABLE,
            'business_name' => $request->business_name, // 🔥 REQUIRED
            'platform' => $request->platform,
            'timezone' => $request->timezone,
            'market_country' => $request->country,
            'currency' => $request->currency,
            'vcc_provider' => $request->vcc_provider,
            'business_manager_id' => $request->business_manager_id,
            'bm_id' => $request->bm_id,
            'website_url' => $request->website_url,
            'account_type' => $request->account_type,
            'personal_profile' => $request->personal_profile,
            'additional_notes' => $request->notes,
            'number_of_accounts' => $request->number_of_accounts,
            'status' => AdAccountRequest::STATUS_PENDING,
        ];

        $data = DB::transaction(function () use ($baseData, $request) {
            $count = (int) $request->number_of_accounts;
            $requestIds = $this->generateRequestIds($count);
            $now = now();
            $baseName = trim((string) $request->req_name);
            $rows = [];

            for ($index = 0; $index < $count; $index++) {
                $rows[] = array_merge($baseData, [
                    'request_id' => $requestIds[$index],
                    'req_name' => $count === 1 ? $baseName : ($baseName . ' ' . ($index + 1)),
                    'type' => AdAccountRequest::TYPE_MASTER,
                    'master_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            AdAccountRequest::insert($rows);

            return AdAccountRequest::where('request_id', $requestIds[0])->first();
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

    public function index(Request $request)
    {
        $query = $this->adAccountRequestQuery($request);
        $requests = $query->orderByDesc('id')->paginate($request->integer('per_page', 10));
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
        return $this->index(request());
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'format' => 'nullable|in:csv,excel',
        ]);

        $requests = $this->adAccountRequestQuery($request)
            ->orderByDesc('id')
            ->get();

        if ($requests->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No ad account requests found for export.',
            ], 404);
        }

        $headers = [
            'request_id',
            'req_name',
            'client_id',
            'client_name',
            'status',
            'type',
            'api',
            'business_name',
            'platform',
            'currency',
            'market_country',
            'timezone',
            'website_url',
            'account_type',
            'number_of_accounts',
            'business_manager_id',
            'business_manager_name',
            'account_name',
            'account_id',
            'sub_user_id',
            'created_by',
            'additional_notes',
            'created_at',
            'updated_at',
            'approved_by',
            'approved_at',
        ];

        $rows = $requests->map(fn ($item) => $this->mapClientExportRow($item))->toArray();

        return $this->exportCsvOrExcel(
            'client-ad-account-requests',
            $headers,
            $rows,
            $validated['format'] ?? 'csv',
            AdAccountRequest::class
        );
    }

    public function update(Request $request, $id)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $request->merge([
            'business_name' => $request->input('business_name', $request->input('company_name')),
            'platform' => $request->input('platform', $request->input('ad_platform')),
            'website_url' => $request->input('website_url', $request->input('company_website_url')),
            'personal_profile' => $request->input('personal_profile', $request->input('personal_facebook_profile_link')),
            'number_of_accounts' => $request->input('number_of_accounts', $request->input('number_of_ad_accounts')),
            'notes' => $request->input('notes', $request->input('note')),
            'api' => $request->input('api', $request->input('api_status')),
        ]);

        $validated = $request->validate([
            'req_name' => ['sometimes', 'string', 'max:255'],
            'business_name' => ['sometimes', 'string', 'max:255'],
            'platform' => ['sometimes', 'string'],
            'timezone' => ['sometimes', 'string'],
            'country' => ['sometimes', 'string'],
            'currency' => ['sometimes', 'string'],
            'vcc_provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'business_manager_id' => ['sometimes', 'nullable', 'exists:business_managers,id'],
            'website_url' => ['sometimes', 'string'],
            'account_type' => ['sometimes', 'string'],
            'personal_profile' => ['sometimes', 'string'],
            'number_of_accounts' => ['sometimes', 'integer', 'min:1'],
            'bm_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:255'],
            // 'api' => ['sometimes', 'string', 'in:enable,disable'],
            'type' => ['prohibited'],
            'master_id' => ['prohibited'],
            'account_name' => ['prohibited'],
            'account_id' => ['prohibited'],
            'card_type' => ['prohibited'],
            'card_number' => ['prohibited'],
        ]);

        $requestData = AdAccountRequest::where('client_id', $clientId)->findOrFail($id);

        if ($requestData->status !== AdAccountRequest::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending ad account requests can be updated.',
            ], 422);
        }

        DB::transaction(function () use ($validated, $requestData) {
            $updateData = [];

            if (array_key_exists('req_name', $validated)) {
                $updateData['req_name'] = $validated['req_name'];
            }

            if (array_key_exists('business_name', $validated)) {
                $updateData['business_name'] = $validated['business_name'];
            }

            if (array_key_exists('platform', $validated)) {
                $updateData['platform'] = $validated['platform'];
            }

            if (array_key_exists('timezone', $validated)) {
                $updateData['timezone'] = $validated['timezone'];
            }

            if (array_key_exists('country', $validated)) {
                $updateData['market_country'] = $validated['country'];
            }

            if (array_key_exists('currency', $validated)) {
                $updateData['currency'] = $validated['currency'];
            }

            if (array_key_exists('vcc_provider', $validated)) {
                $updateData['vcc_provider'] = $validated['vcc_provider'];
            }

            if (array_key_exists('business_manager_id', $validated)) {
                $updateData['business_manager_id'] = $validated['business_manager_id'];
            }

            if (array_key_exists('website_url', $validated)) {
                $updateData['website_url'] = $validated['website_url'];
            }

            if (array_key_exists('account_type', $validated)) {
                $updateData['account_type'] = $validated['account_type'];
            }

            if (array_key_exists('personal_profile', $validated)) {
                $updateData['personal_profile'] = $validated['personal_profile'];
            }

            if (array_key_exists('number_of_accounts', $validated)) {
                $updateData['number_of_accounts'] = $validated['number_of_accounts'];
            }

            if (array_key_exists('bm_id', $validated)) {
                $updateData['bm_id'] = $validated['bm_id'];
            }

            if (array_key_exists('notes', $validated)) {
                $updateData['additional_notes'] = $validated['notes'];
            }

            if (array_key_exists('api', $validated)) {
                $updateData['api'] = $validated['api'] ?? AdAccountRequest::API_ENABLE;
            }

            if (!empty($updateData)) {
                $requestData->update($updateData);
            }
        });

        $updatedRequest = $requestData->fresh([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'businessManager',
        ]);
        $updatedRequest->client_name = $this->resolveClientName($updatedRequest);
        $updatedRequest->sub_user_id = $updatedRequest->sub_user_id;
        $updatedRequest->created_by = optional($updatedRequest->creatorUser)->name
            ?? optional(optional($updatedRequest->client)->primaryAdmin)->name
            ?? $updatedRequest->client_name;
        $updatedRequest->business_manager_name = optional($updatedRequest->businessManager)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Ad account request updated.',
            'data' => $updatedRequest,
        ]);
    }

    private function adAccountRequestQuery(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $query = AdAccountRequest::with([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'businessManager',
        ])->where('client_id', $clientId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('req_name', 'like', "%{$search}%")
                    ->orWhere('request_id', 'like', "%{$search}%")
                    ->orWhere('bm_id', 'like', "%{$search}%")
                    ->orWhere('market_country', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%")
                    ->orWhere('account_id', 'like', "%{$search}%")
                    ->orWhere('business_manager_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('start_date')) {
            try {
                $start = Carbon::parse($request->start_date)->startOfDay();
                $query->where('created_at', '>=', $start);
            } catch (\Throwable) {
                // ignore invalid dates
            }
        }

        if ($request->filled('end_date')) {
            try {
                $end = Carbon::parse($request->end_date)->endOfDay();
                $query->where('created_at', '<=', $end);
            } catch (\Throwable) {
                // ignore invalid dates
            }
        }

        return $query;
    }

    private function mapClientExportRow(AdAccountRequest $item): array
    {
        $clientName = $this->resolveClientName($item);
        $createdBy = optional($item->creatorUser)->name
            ?? optional(optional($item->client)->primaryAdmin)->name
            ?? $clientName;

        return [
            'request_id' => $item->request_id,
            'req_name' => $item->req_name,
            'client_id' => $item->client_id,
            'client_name' => $clientName,
            'status' => $item->status,
            'type' => $item->type,
            'api' => $item->api,
            'business_name' => $item->business_name,
            'platform' => $item->platform,
            'currency' => $item->currency,
            'market_country' => $item->market_country,
            'timezone' => $item->timezone,
            'website_url' => $item->website_url,
            'account_type' => $item->account_type,
            'number_of_accounts' => $item->number_of_accounts,
            'business_manager_id' => $item->business_manager_id,
            'business_manager_name' => optional($item->businessManager)->name,
            'account_name' => $item->account_name,
            'account_id' => $item->account_id,
            'sub_user_id' => $item->sub_user_id,
            'created_by' => $createdBy,
            'additional_notes' => $item->additional_notes,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
            'approved_by' => $item->approved_by,
            'approved_at' => $item->approved_at,
        ];
    }

    private function resolveClientName(AdAccountRequest $request): ?string
    {
        return data_get($request, 'client.clientName')
            ?? data_get($request, 'client.client_name')
            ?? data_get($request, 'client.name');
    }

    private function generateRequestIds(int $count): array
    {
        $count = max(1, $count);
        $startId = ((int) AdAccountRequest::max('id')) + 1;
        $ids = [];

        for ($offset = 0; $offset < $count; $offset++) {
            $ids[] = 'ACC-' . str_pad((string) ($startId + $offset), 4, '0', STR_PAD_LEFT);
        }

        return $ids;
    }
}
