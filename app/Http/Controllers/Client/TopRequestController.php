<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CsvExcelResponse;
use App\Models\TopRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TopRequestController extends Controller
{
    use CsvExcelResponse;

    public function store(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $validated = $request->validate([
            'ad_account_request_id' => [
                'required',
                'integer',
                Rule::exists('ad_account_requests', 'id')->where(function ($query) use ($clientId) {
                    $query->where('client_id', $clientId);
                }),
            ],
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
        ]);

        $lastId = (int) TopRequest::max('id');
        $nextId = $lastId + 1;
        $requestId = 'ACCTOP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $data = TopRequest::create([
            'client_id' => $clientId,
            'sub_user_id' => Auth::id(),
            'ad_account_request_id' => $validated['ad_account_request_id'],
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'status' => TopRequest::STATUS_PENDING,
            'request_id' => $requestId,
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'top_request_created',
            title: 'New Topup Request',
            message: Auth::user()->name . " submitted topup request {$data->request_id}.",
            meta: [
                'top_request_id' => $data->id,
                'request_id' => $data->request_id,
                'client_id' => $clientId,
                'client_name' => Auth::user()->name,
                'ad_account_request_id' => $data->ad_account_request_id,
                'amount' => $data->amount,
                'currency' => $data->currency,
                'status' => $data->status,
            ]
        );

        $data->load([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'adAccountRequest:id,request_id,business_name,platform,status',
        ]);
        $data->client_name = $this->resolveClientName($data);
        $data->created_by = optional($data->creatorUser)->name
            ?? optional(optional($data->client)->primaryAdmin)->name
            ?? $data->client_name;

        return response()->json([
            'status' => 'success',
            'message' => 'Top request submitted.',
            'data' => $data,
        ], 201);
    }

    public function index(Request $request)
    {
        $query = $this->topRequestQuery($request);

        $data = $query->orderByDesc('id')->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = $this->resolveClientName($item);
            $item->created_by = optional($item->creatorUser)->name
                ?? optional(optional($item->client)->primaryAdmin)->name
                ?? $item->client_name;
            return $item;
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function myRequests(Request $request)
    {
        return $this->index($request);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'ad_account_request_id' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:csv,excel',
        ]);

        $requests = $this->topRequestQuery($request)
            ->orderByDesc('id')
            ->get();

        if ($requests->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No top requests found for export.',
            ], 404);
        }

        $headers = [
            'request_id',
            'client_id',
            'client_name',
            'sub_user_id',
            'created_by',
            'ad_account_request_id',
            'ad_account_request_number',
            'ad_account_business_name',
            'ad_account_platform',
            'ad_account_status',
            'amount',
            'currency',
            'status',
            'created_at',
            'updated_at',
        ];

        $rows = $requests->map(fn ($item) => $this->mapTopRequestExportRow($item))->toArray();

        return $this->exportCsvOrExcel(
            'client-top-requests',
            $headers,
            $rows,
            $validated['format'] ?? 'csv',
            TopRequest::class
        );
    }

    private function resolveClientName(TopRequest $request): ?string
    {
        return data_get($request, 'client.clientName')
            ?? data_get($request, 'client.client_name')
            ?? data_get($request, 'client.name');
    }

    private function topRequestQuery(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $query = TopRequest::with([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
            'adAccountRequest:id,request_id,business_name,platform,status',
        ])->where('client_id', $clientId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('ad_account_request_id') && $request->ad_account_request_id !== 'all') {
            $query->where('ad_account_request_id', $request->ad_account_request_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('currency', 'like', "%{$search}%")
                    ->orWhere('amount', 'like', "%{$search}%")
                    ->orWhereHas('adAccountRequest', function ($sub) use ($search) {
                        $sub->where('request_id', 'like', "%{$search}%")
                            ->orWhere('business_name', 'like', "%{$search}%")
                            ->orWhere('platform', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('start_date')) {
            try {
                $start = Carbon::parse($request->start_date)->startOfDay();
                $query->where('created_at', '>=', $start);
            } catch (\Throwable) {
                //
            }
        }

        if ($request->filled('end_date')) {
            try {
                $end = Carbon::parse($request->end_date)->endOfDay();
                $query->where('created_at', '<=', $end);
            } catch (\Throwable) {
                //
            }
        }

        return $query;
    }

    private function mapTopRequestExportRow(TopRequest $request): array
    {
        $clientName = $this->resolveClientName($request);
        $createdBy = optional($request->creatorUser)->name
            ?? optional(optional($request->client)->primaryAdmin)->name
            ?? $clientName;
        $adAccount = $request->adAccountRequest;

        return [
            'request_id' => $request->request_id,
            'client_id' => $request->client_id,
            'client_name' => $clientName,
            'sub_user_id' => $request->sub_user_id,
            'created_by' => $createdBy,
            'ad_account_request_id' => $request->ad_account_request_id,
            'ad_account_request_number' => optional($adAccount)->request_id,
            'ad_account_business_name' => optional($adAccount)->business_name,
            'ad_account_platform' => optional($adAccount)->platform,
            'ad_account_status' => optional($adAccount)->status,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'status' => $request->status,
            'created_at' => $request->created_at,
            'updated_at' => $request->updated_at,
        ];
    }
}
