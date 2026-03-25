<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CsvExcelResponse;
use App\Models\ExchangeRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ExchangeRequestController extends Controller
{
    use CsvExcelResponse;

    public function store(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $validated = $request->validate([
            'base_currency' => ['required', 'string', 'max:10'],
            'converion_currency' => ['required', 'string', 'max:10', 'different:base_currency'],
            'request_amount' => ['required', 'numeric', 'min:0.01'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'convertion_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $data = $this->buildAmounts($validated);

        $lastId = (int) ExchangeRequest::max('id');
        $nextId = $lastId + 1;
        $requestId = 'EXH-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);

        $exchangeRequest = ExchangeRequest::create([
            'client_id' => $clientId,
            'sub_user_id' => Auth::id(),
            'based_cur' => strtoupper($validated['base_currency']),
            'base_currency' => strtoupper($validated['base_currency']),
            'convertion_cur' => strtoupper($validated['converion_currency']),
            'converion_currency' => strtoupper($validated['converion_currency']),
            'request_amount' => $data['request_amount'],
            'service_fee' => $data['service_fee'],
            'final_amount' => $data['return_amount'],
            'total_deduction' => $data['total_deduction'],
            'return_amount' => $data['return_amount'],
            'convertion_rate' => $data['convertion_rate'],
            'status' => ExchangeRequest::STATUS_PENDING,
            'request_id' => $requestId,
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'exchange_request_created',
            title: 'New Exchange Request',
            message: Auth::user()->name . " submitted exchange request {$exchangeRequest->request_id}.",
            meta: [
                'exchange_request_id' => $exchangeRequest->id,
                'request_id' => $exchangeRequest->request_id,
                'client_id' => $clientId,
                'client_name' => Auth::user()->name,
                'base_currency' => $exchangeRequest->base_currency,
                'converion_currency' => $exchangeRequest->converion_currency,
                'request_amount' => $exchangeRequest->request_amount,
                'service_fee' => $exchangeRequest->service_fee,
                'total_deduction' => $exchangeRequest->total_deduction,
                'return_amount' => $exchangeRequest->return_amount,
                'convertion_rate' => $exchangeRequest->convertion_rate,
                'status' => $exchangeRequest->status,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Exchange request submitted.',
            'data' => tap($exchangeRequest->load([
                'client.primaryAdmin:id,name,email',
                'creatorUser:id,name',
            ]), function ($item) {
                $item->client_name = $this->resolveClientName($item);
                $item->created_by = optional($item->creatorUser)->name
                    ?? optional(optional($item->client)->primaryAdmin)->name
                    ?? $item->client_name;
            }),
        ], 201);
    }

    public function index(Request $request)
    {
        $query = $this->exchangeRequestQuery($request);

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
            'base_currency' => 'nullable|string',
            'converion_currency' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:csv,excel',
        ]);

        $requests = $this->exchangeRequestQuery($request)
            ->orderByDesc('id')
            ->get();

        if ($requests->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No exchange requests found for export.',
            ], 404);
        }

        $headers = [
            'request_id',
            'client_id',
            'client_name',
            'sub_user_id',
            'created_by',
            'based_cur',
            'base_currency',
            'convertion_cur',
            'convertion_currency',
            'request_amount',
            'service_fee',
            'total_deduction',
            'return_amount',
            'final_amount',
            'convertion_rate',
            'status',
            'approved_by',
            'approved_at',
            'created_at',
            'updated_at',
        ];

        $rows = $requests->map(fn ($item) => $this->mapExchangeExportRow($item))->toArray();

        return $this->exportCsvOrExcel(
            'client-exchange-requests',
            $headers,
            $rows,
            $validated['format'] ?? 'csv',
            ExchangeRequest::class
        );
    }

    public function show($id)
    {
        $clientId = (int) request()->attributes->get('current_client_id');

        $exchangeRequest = ExchangeRequest::with([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
            'approver:id,name,email',
        ])
            ->where('client_id', $clientId)
            ->findOrFail($id);
        $exchangeRequest->client_name = $this->resolveClientName($exchangeRequest);
        $exchangeRequest->created_by = optional($exchangeRequest->creatorUser)->name
            ?? optional(optional($exchangeRequest->client)->primaryAdmin)->name
            ?? $exchangeRequest->client_name;

        return response()->json([
            'status' => 'success',
            'data' => $exchangeRequest,
        ]);
    }

    public function update(Request $request, $id)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $exchangeRequest = ExchangeRequest::where('client_id', $clientId)->findOrFail($id);

        if ($exchangeRequest->status !== ExchangeRequest::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending exchange requests can be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'base_currency' => ['sometimes', 'string', 'max:10'],
            'converion_currency' => ['sometimes', 'string', 'max:10', 'different:base_currency'],
            'request_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'service_fee' => ['sometimes', 'numeric', 'min:0'],
            'convertion_rate' => ['sometimes', 'numeric', 'gt:0'],
        ]);

        $effective = [
            'request_amount' => array_key_exists('request_amount', $validated)
                ? $validated['request_amount']
                : $exchangeRequest->request_amount,
            'service_fee' => array_key_exists('service_fee', $validated)
                ? $validated['service_fee']
                : $exchangeRequest->service_fee,
            'convertion_rate' => array_key_exists('convertion_rate', $validated)
                ? $validated['convertion_rate']
                : $exchangeRequest->convertion_rate,
        ];

        $calculated = $this->buildAmounts($effective);

        $exchangeRequest->update([
            'based_cur' => array_key_exists('base_currency', $validated)
                ? strtoupper($validated['base_currency'])
                : ($exchangeRequest->based_cur ?? $exchangeRequest->base_currency),
            'base_currency' => array_key_exists('base_currency', $validated)
                ? strtoupper($validated['base_currency'])
                : ($exchangeRequest->base_currency ?? $exchangeRequest->based_cur),
            'convertion_cur' => array_key_exists('converion_currency', $validated)
                ? strtoupper($validated['converion_currency'])
                : ($exchangeRequest->convertion_cur ?? $exchangeRequest->converion_currency),
            'converion_currency' => array_key_exists('converion_currency', $validated)
                ? strtoupper($validated['converion_currency'])
                : ($exchangeRequest->converion_currency ?? $exchangeRequest->convertion_cur),
            'request_amount' => $calculated['request_amount'],
            'service_fee' => $calculated['service_fee'],
            'final_amount' => $calculated['return_amount'],
            'total_deduction' => $calculated['total_deduction'],
            'return_amount' => $calculated['return_amount'],
            'convertion_rate' => $calculated['convertion_rate'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Exchange request updated.',
            'data' => tap($exchangeRequest->fresh([
                'client.primaryAdmin:id,name,email',
                'creatorUser:id,name',
                'approver:id,name,email',
            ]), function ($item) {
                $item->client_name = $this->resolveClientName($item);
                $item->created_by = optional($item->creatorUser)->name
                    ?? optional(optional($item->client)->primaryAdmin)->name
                    ?? $item->client_name;
            }),
        ]);
    }

    public function destroy($id)
    {
        $clientId = (int) request()->attributes->get('current_client_id');
        $exchangeRequest = ExchangeRequest::where('client_id', $clientId)->findOrFail($id);

        if ($exchangeRequest->status !== ExchangeRequest::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending exchange requests can be deleted.',
            ], 422);
        }

        $exchangeRequest->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Exchange request deleted.',
        ]);
    }

    private function buildAmounts(array $payload): array
    {
        $requestAmount = round((float) $payload['request_amount'], 2);
        $serviceFee = round((float) ($payload['service_fee'] ?? 0), 2);
        $convertionRate = round((float) $payload['convertion_rate'], 6);

        return [
            'request_amount' => $requestAmount,
            'service_fee' => $serviceFee,
            'total_deduction' => round($requestAmount + $serviceFee, 2),
            'return_amount' => round($requestAmount * $convertionRate, 2),
            'convertion_rate' => $convertionRate,
        ];
    }

    private function resolveClientName(ExchangeRequest $exchangeRequest): ?string
    {
        return data_get($exchangeRequest, 'client.clientName')
            ?? data_get($exchangeRequest, 'client.client_name')
            ?? data_get($exchangeRequest, 'client.name');
    }

    private function exchangeRequestQuery(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $query = ExchangeRequest::with([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
            'approver:id,name,email',
        ])->where('client_id', $clientId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('base_currency') && $request->base_currency !== 'all') {
            $query->where('base_currency', strtoupper((string) $request->base_currency));
        }

        if ($request->filled('converion_currency') && $request->converion_currency !== 'all') {
            $query->where('converion_currency', strtoupper((string) $request->converion_currency));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('base_currency', 'like', "%{$search}%")
                    ->orWhere('converion_currency', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('request_amount', 'like', "%{$search}%")
                    ->orWhere('service_fee', 'like', "%{$search}%")
                    ->orWhere('total_deduction', 'like', "%{$search}%")
                    ->orWhere('return_amount', 'like', "%{$search}%")
                    ->orWhere('convertion_rate', 'like', "%{$search}%");
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

    private function mapExchangeExportRow(ExchangeRequest $exchangeRequest): array
    {
        $clientName = $this->resolveClientName($exchangeRequest);
        $createdBy = optional($exchangeRequest->creatorUser)->name
            ?? optional(optional($exchangeRequest->client)->primaryAdmin)->name
            ?? $clientName;

        return [
            'request_id' => $exchangeRequest->request_id,
            'client_id' => $exchangeRequest->client_id,
            'client_name' => $clientName,
            'sub_user_id' => $exchangeRequest->sub_user_id,
            'created_by' => $createdBy,
            'based_cur' => $exchangeRequest->based_cur,
            'base_currency' => $exchangeRequest->base_currency,
            'convertion_cur' => $exchangeRequest->convertion_cur,
            'convertion_currency' => $exchangeRequest->convertion_currency,
            'request_amount' => $exchangeRequest->request_amount,
            'service_fee' => $exchangeRequest->service_fee,
            'total_deduction' => $exchangeRequest->total_deduction,
            'return_amount' => $exchangeRequest->return_amount,
            'final_amount' => $exchangeRequest->final_amount,
            'convertion_rate' => $exchangeRequest->convertion_rate,
            'status' => $exchangeRequest->status,
            'approved_by' => optional($exchangeRequest->approver)->name ?? $exchangeRequest->approved_by,
            'approved_at' => $exchangeRequest->approved_at,
            'created_at' => $exchangeRequest->created_at,
            'updated_at' => $exchangeRequest->updated_at,
        ];
    }
}
