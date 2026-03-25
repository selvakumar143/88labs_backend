<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\CsvExcelResponse;
use App\Http\Controllers\Controller;
use App\Models\WalletTopup;
use App\Support\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class WalletTopupController extends Controller
{
    use CsvExcelResponse;

    public function store(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');

        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'request_amount' => 'nullable|numeric|min:0.01|required_without:amount',
            'service_fee' => 'nullable|numeric|min:0',
            'transaction_hash' => 'required|string'
        ]);

        $requestAmount = (float) ($validated['request_amount'] ?? 0 );
        $amount = (float) ($validated['amount'] ?? 0);
        $serviceFee = round((float) ($validated['service_fee'] ?? 0), 2);

        $lastId = WalletTopup::max('id') + 1;
        $requestId = 'TOP-' . str_pad($lastId, 4, '0', STR_PAD_LEFT);

        $currency =$validated['currency'] ; // You can modify this to accept currency from the request if needed
        $data = WalletTopup::create([
            'request_id' => $requestId,
            'client_id' => $clientId,
            'sub_user_id' => Auth::id(),
            "currency" => $currency,
            'amount' => $amount,
            'request_amount' => $requestAmount,
            'service_fee' => $serviceFee,
            'transaction_hash' => $validated['transaction_hash'],
            'status' => WalletTopup::STATUS_PENDING
        ]);

        NotificationDispatcher::notifyAdmins(
            eventType: 'wallet_topup_created',
            title: 'New Wallet Topup Request',
            message: Auth::user()->name . " submitted wallet topup {$data->request_id}.",
            meta: [
                'wallet_topup_id' => $data->id,
                'request_id' => $data->request_id,
                'client_id' => $clientId,
                'client_name' => Auth::user()->name,
                'amount' => $data->amount,
                'request_amount' => $data->request_amount,
                'service_fee' => $data->service_fee,
                'status' => $data->status,
            ]
        );

        $data->load([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
        ]);
        $data->client_name = $this->resolveClientName($data);
        $data->created_by = optional($data->creatorUser)->name
            ?? optional(optional($data->client)->primaryAdmin)->name
            ?? $data->client_name;

        return response()->json([
            'status' => 'success',
            'message' => 'Topup request submitted',
            'data' => $data
        ]);
    }

    public function myRequests(Request $request)
    {
        $query = $this->walletTopupQuery($request);

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
            'data' => $data
        ]);
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:csv,excel',
        ]);

        $topups = $this->walletTopupQuery($request)
            ->orderByDesc('id')
            ->get();

        if ($topups->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No wallet topup requests found for export.',
            ], 404);
        }

        $headers = [
            'request_id',
            'client_id',
            'client_name',
            'sub_user_id',
            'created_by',
            'amount',
            'request_amount',
            'service_fee',
            'total_amount',
            'currency',
            'payment_mode',
            'transaction_hash',
            'status',
            'approved_by',
            'approved_at',
            'created_at',
            'updated_at',
        ];

        $rows = $topups->map(fn ($item) => $this->mapTopupExportRow($item))->toArray();

        return $this->exportCsvOrExcel(
            'client-wallet-topups',
            $headers,
            $rows,
            $validated['format'] ?? 'csv',
            WalletTopup::class
        );
    }

    private function resolveClientName(WalletTopup $topup): ?string
    {
        return data_get($topup, 'client.clientName')
            ?? data_get($topup, 'client.client_name')
            ?? data_get($topup, 'client.name');
    }

    private function walletTopupQuery(Request $request)
    {
        $clientId = (int) $request->attributes->get('current_client_id');
        $query = WalletTopup::with([
            'client.primaryAdmin:id,name',
            'creatorUser:id,name',
        ])->where('client_id', $clientId);

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('request_id', 'like', "%{$search}%")
                    ->orWhere('transaction_hash', 'like', "%{$search}%");
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

    private function mapTopupExportRow(WalletTopup $topup): array
    {
        $clientName = $this->resolveClientName($topup);
        $createdBy = optional($topup->creatorUser)->name
            ?? optional(optional($topup->client)->primaryAdmin)->name
            ?? $clientName;

        return [
            'request_id' => $topup->request_id,
            'client_id' => $topup->client_id,
            'client_name' => $clientName,
            'sub_user_id' => $topup->sub_user_id,
            'created_by' => $createdBy,
            'amount' => $topup->amount,
            'request_amount' => $topup->request_amount,
            'service_fee' => $topup->service_fee,
            'total_amount' => $topup->total_amount,
            'currency' => $topup->currency,
            'payment_mode' => $topup->payment_mode,
            'transaction_hash' => $topup->transaction_hash,
            'status' => $topup->status,
            'approved_by' => optional($topup->approver)->name ?? $topup->approved_by,
            'approved_at' => $topup->approved_at,
            'created_at' => $topup->created_at,
            'updated_at' => $topup->updated_at,
        ];
    }
}
