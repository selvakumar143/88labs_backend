<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRequest;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExchangeRequestController extends Controller
{
    public function store(Request $request)
    {
        $tenantOwnerUserId = (int) $request->attributes->get('current_client_owner_user_id');

        $validated = $request->validate([
            'base_currency' => ['required', 'string', 'max:10'],
            'converion_currency' => ['required', 'string', 'max:10', 'different:base_currency'],
            'request_amount' => ['required', 'numeric', 'min:0.01'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'convertion_rate' => ['required', 'numeric', 'gt:0'],
        ]);

        $data = $this->buildAmounts($validated);

        $exchangeRequest = ExchangeRequest::create([
            'client_id' => $tenantOwnerUserId,
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
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'exchange_request_created',
            title: 'New Exchange Request',
            message: Auth::user()->name . " submitted exchange request #{$exchangeRequest->id}.",
            meta: [
                'exchange_request_id' => $exchangeRequest->id,
                'client_id' => $tenantOwnerUserId,
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
                'client:id,name,email',
                'clientProfileByUserId:id,primary_admin_user_id,clientName',
                'clientProfileByClientId:id,primary_admin_user_id,clientName',
            ]), function ($item) {
                $item->client_name = $this->resolveClientName($item);
            }),
        ], 201);
    }

    public function index(Request $request)
    {
        $tenantOwnerUserId = (int) $request->attributes->get('current_client_owner_user_id');

        $query = ExchangeRequest::with([
            'client:id,name,email',
            'clientProfileByUserId:id,primary_admin_user_id,clientName',
            'clientProfileByClientId:id,primary_admin_user_id,clientName',
            'approver:id,name,email',
        ])
            ->where('client_id', $tenantOwnerUserId);

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

        $data = $query->latest()->paginate($request->integer('per_page', 10));
        $data->getCollection()->transform(function ($item) {
            $item->client_name = $this->resolveClientName($item);
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

    public function show($id)
    {
        $tenantOwnerUserId = (int) request()->attributes->get('current_client_owner_user_id');

        $exchangeRequest = ExchangeRequest::with([
            'client:id,name,email',
            'clientProfileByUserId:id,primary_admin_user_id,clientName',
            'clientProfileByClientId:id,primary_admin_user_id,clientName',
            'approver:id,name,email',
        ])
            ->where('client_id', $tenantOwnerUserId)
            ->findOrFail($id);
        $exchangeRequest->client_name = $this->resolveClientName($exchangeRequest);

        return response()->json([
            'status' => 'success',
            'data' => $exchangeRequest,
        ]);
    }

    public function update(Request $request, $id)
    {
        $tenantOwnerUserId = (int) $request->attributes->get('current_client_owner_user_id');
        $exchangeRequest = ExchangeRequest::where('client_id', $tenantOwnerUserId)->findOrFail($id);

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
                'client:id,name,email',
                'clientProfileByUserId:id,primary_admin_user_id,clientName',
                'clientProfileByClientId:id,primary_admin_user_id,clientName',
                'approver:id,name,email',
            ]), function ($item) {
                $item->client_name = $this->resolveClientName($item);
            }),
        ]);
    }

    public function destroy($id)
    {
        $tenantOwnerUserId = (int) request()->attributes->get('current_client_owner_user_id');
        $exchangeRequest = ExchangeRequest::where('client_id', $tenantOwnerUserId)->findOrFail($id);

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
        return optional($exchangeRequest->clientProfileByUserId)->clientName
            ?? optional($exchangeRequest->clientProfileByClientId)->clientName
            ?? optional(optional($exchangeRequest->client)->client)->clientName
            ?? optional($exchangeRequest->client)->name;
    }
}
