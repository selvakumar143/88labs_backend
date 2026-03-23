<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;

class ExchangeRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = ExchangeRequest::with([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
            'approver:id,name,email',
        ]);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('client_id') && $request->client_id !== 'all') {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('client_name')) {
            $clientName = trim((string) $request->client_name);
            $query->where(function ($q) use ($clientName) {
                $q->whereHas('client', function ($sub) use ($clientName) {
                    $sub->where('clientName', 'like', "%{$clientName}%");

                    if (Schema::hasColumn('clients', 'client_name')) {
                        $sub->orWhere('client_name', 'like', "%{$clientName}%");
                    }
                });
            });
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
                    ->orWhere('request_id','like',"%{$search}%")
                    ->orWhere('request_amount', 'like', "%{$search}%")
                    ->orWhere('service_fee', 'like', "%{$search}%")
                    ->orWhere('total_deduction', 'like', "%{$search}%")
                    ->orWhere('return_amount', 'like', "%{$search}%")
                    ->orWhere('convertion_rate', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($sub) use ($search) {
                        $sub->where('clientName', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");

                        if (Schema::hasColumn('clients', 'client_name')) {
                            $sub->orWhere('client_name', 'like', "%{$search}%");
                        }
                    });
            });
        }

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

    public function show($id)
    {
        $exchangeRequest = ExchangeRequest::with([
            'client.primaryAdmin:id,name,email',
            'creatorUser:id,name',
            'approver:id,name,email',
        ])->findOrFail($id);
        $exchangeRequest->client_name = $this->resolveClientName($exchangeRequest);
        $exchangeRequest->created_by = optional($exchangeRequest->creatorUser)->name
            ?? optional(optional($exchangeRequest->client)->primaryAdmin)->name
            ?? $exchangeRequest->client_name;

        return response()->json([
            'status' => 'success',
            'data' => $exchangeRequest,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'base_currency' => ['required', 'string', 'max:10'],
            'converion_currency' => ['required', 'string', 'max:10', 'different:base_currency'],
            'request_amount' => ['required', 'numeric', 'min:0.01'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'convertion_rate' => ['required', 'numeric', 'gt:0'],
            'status' => ['sometimes', Rule::in([
                ExchangeRequest::STATUS_PENDING,
                ExchangeRequest::STATUS_APPROVED,
                ExchangeRequest::STATUS_REJECTED,
            ])],
        ]);

        $data = $this->buildAmounts($validated);
        $status = $validated['status'] ?? ExchangeRequest::STATUS_PENDING;

        $exchangeRequest = ExchangeRequest::create([
            'client_id' => $validated['client_id'],
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
            'status' => $status,
            'approved_by' => $status === ExchangeRequest::STATUS_PENDING ? null : Auth::id(),
            'approved_at' => $status === ExchangeRequest::STATUS_PENDING ? null : now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Exchange request created.',
            'data' => tap($exchangeRequest->load([
                'client.primaryAdmin:id,name,email',
                'creatorUser:id,name',
                'approver:id,name,email',
            ]), function ($item) {
                $item->client_name = $this->resolveClientName($item);
                $item->created_by = optional($item->creatorUser)->name
                    ?? optional(optional($item->client)->primaryAdmin)->name
                    ?? $item->client_name;
            }),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $exchangeRequest = ExchangeRequest::findOrFail($id);

        $validated = $request->validate([
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'base_currency' => ['sometimes', 'string', 'max:10'],
            'converion_currency' => ['sometimes', 'string', 'max:10', 'different:base_currency'],
            'request_amount' => ['sometimes', 'numeric', 'min:0.01'],
            'service_fee' => ['sometimes', 'numeric', 'min:0'],
            'convertion_rate' => ['sometimes', 'numeric', 'gt:0'],
            'status' => ['sometimes', Rule::in([
                ExchangeRequest::STATUS_PENDING,
                ExchangeRequest::STATUS_APPROVED,
                ExchangeRequest::STATUS_REJECTED,
            ])],
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

        $status = $validated['status'] ?? $exchangeRequest->status;

        $exchangeRequest->update([
            'client_id' => $validated['client_id'] ?? $exchangeRequest->client_id,
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
            'status' => $status,
            'approved_by' => $status === ExchangeRequest::STATUS_PENDING ? null : Auth::id(),
            'approved_at' => $status === ExchangeRequest::STATUS_PENDING ? null : now(),
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

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                ExchangeRequest::STATUS_APPROVED,
                ExchangeRequest::STATUS_REJECTED,
            ])],
        ]);

        $exchangeRequest = ExchangeRequest::with('client.primaryAdmin')->findOrFail($id);
        $previousStatus = $exchangeRequest->status;

        $exchangeRequest->update([
            'status' => $validated['status'],
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        if ($previousStatus !== $validated['status']) {
            NotificationDispatcher::notifyClient(
                client: optional($exchangeRequest->client)->primaryAdmin,
                eventType: 'exchange_request_status_updated',
                title: 'Exchange Request Updated',
                message: "Your exchange request #{$exchangeRequest->id} is {$validated['status']}.",
                meta: [
                    'exchange_request_id' => $exchangeRequest->id,
                    'status' => $validated['status'],
                    'base_currency' => $exchangeRequest->base_currency,
                    'converion_currency' => $exchangeRequest->converion_currency,
                    'request_amount' => $exchangeRequest->request_amount,
                    'service_fee' => $exchangeRequest->service_fee,
                    'total_deduction' => $exchangeRequest->total_deduction,
                    'return_amount' => $exchangeRequest->return_amount,
                    'convertion_rate' => $exchangeRequest->convertion_rate,
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Exchange request status updated.',
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
        $exchangeRequest = ExchangeRequest::findOrFail($id);
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
}
