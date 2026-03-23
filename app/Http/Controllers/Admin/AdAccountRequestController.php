<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CsvExcelResponse;
use App\Models\AdAccountRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdAccountRequestController extends Controller
{
    use CsvExcelResponse;

    public function index(Request $request)
    {
        $data = $this->adAccountRequestQuery($request)
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = $this->resolveClientName($item);
            $item->created_by = optional($item->creatorUser)->name
                ?? optional(optional($item->client)->primaryAdmin)->name
                ?? $item->client_name;
            $item->business_manager_name = optional($item->businessManager)->name;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'client_id' => 'nullable',
            'search' => 'nullable|string',
            'format' => 'nullable|in:csv,excel',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
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

        $rows = $requests->map(fn ($item) => $this->mapAdminExportRow($item))->toArray();

        return $this->exportCsvOrExcel(
            'admin-ad-account-requests-' . now()->format('Ymd_His'),
            $headers,
            $rows,
            $validated['format'] ?? 'csv'
        );
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                AdAccountRequest::STATUS_APPROVED,
                AdAccountRequest::STATUS_REJECTED,
                AdAccountRequest::STATUS_PENDING,
            ])],
            'req_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'api' => ['sometimes', 'nullable', Rule::in([
                AdAccountRequest::API_ENABLE,
                AdAccountRequest::API_DISABLE,
            ])],
            'business_manager_id' => ['sometimes', 'nullable', 'exists:business_managers,id'],
            'vcc_provider' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'additional_notes' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_preference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'account_id' => ['sometimes', 'nullable', 'string', 'max:255'],
            'card_type' => ['sometimes', 'nullable', 'string', 'max:100'],
            'card_number' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);

        $requestData = AdAccountRequest::findOrFail($id);
        $previousStatus = $requestData->status;

        DB::transaction(function () use ($validated, $requestData) {
            $updateData = [
                'status' => $validated['status'],
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ];

            if (array_key_exists('business_manager_id', $validated)) {
                $updateData['business_manager_id'] = $validated['business_manager_id'];
            }

            if (array_key_exists('req_name', $validated)) {
                $updateData['req_name'] = $validated['req_name'];
            }

            if (array_key_exists('api', $validated)) {
                $updateData['api'] = $validated['api'] ?? AdAccountRequest::API_ENABLE;
            }

            if (array_key_exists('vcc_provider', $validated)) {
                $updateData['vcc_provider'] = $validated['vcc_provider'];
            }

            if (array_key_exists('account_name', $validated)) {
                $updateData['account_name'] = $validated['account_name'];
            }

            if (array_key_exists('account_preference', $validated)) {
                $updateData['account_preference'] = $validated['account_preference'];
            }

            if (array_key_exists('account_id', $validated)) {
                $updateData['account_id'] = $validated['account_id'];
            }

            if (array_key_exists('card_type', $validated)) {
                $updateData['card_type'] = $validated['card_type'];
            }

            if (array_key_exists('card_number', $validated)) {
                $updateData['card_number'] = $validated['card_number'];
            }
         
            if (array_key_exists('additional_notes', $validated)) {
                $updateData['additional_notes'] = $validated['additional_notes'];
            }
            $requestData->update($updateData);
        });

        if ($previousStatus !== $validated['status']) {
            NotificationDispatcher::notifyClient(
                client: optional($requestData->client)->primaryAdmin,
                eventType: 'ad_account_request_status_updated',
                title: 'Ad Account Request Updated',
                message: "Your ad account request {$requestData->request_id} is {$validated['status']}.",
                meta: [
                    'ad_account_request_id' => $requestData->id,
                    'request_id' => $requestData->request_id,
                    'status' => $validated['status'],
                ]
            );
        }

        $updatedRequest = $requestData->fresh([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
            'businessManager',
        ]);
        $updatedRequest->client_name = $this->resolveClientName($updatedRequest);
        $updatedRequest->created_by = optional($updatedRequest->creatorUser)->name
            ?? optional(optional($updatedRequest->client)->primaryAdmin)->name
            ?? $updatedRequest->client_name;
        $updatedRequest->business_manager_name = optional($updatedRequest->businessManager)->name;

        return response()->json([
            'status' => 'success',
            'message' => 'Request status updated.',
            'data' => $updatedRequest,
        ]);
    }

    private function adAccountRequestQuery(Request $request)
    {
        $query = AdAccountRequest::with([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
            'businessManager',
        ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $clientId = $request->input('client_id', $request->input('client'));
        if (!empty($clientId) && $clientId !== 'all') {
            $query->where('client_id', $clientId);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('req_name', 'like', "%{$search}%")
                  ->orWhere('request_id', 'like', "%{$search}%")
                  ->orWhere('account_id', 'like', "%{$search}%")
                  ->orWhere('account_name', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('api', 'like', "%{$search}%")
                  ->orWhere('vcc_provider', 'like', "%{$search}%")
                  ->orWhereHas('client', function ($sub) use ($search) {
                      $sub->where('clientName', 'like', "%{$search}%")
                          ->orWhere('email', 'like', "%{$search}%");

                      if (Schema::hasColumn('clients', 'client_name')) {
                          $sub->orWhere('client_name', 'like', "%{$search}%");
                      }
                  })
                  ->orWhereHas('businessManager', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%");
                  });
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

    private function mapAdminExportRow(AdAccountRequest $item): array
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
}
