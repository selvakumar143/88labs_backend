<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRequest;
use App\Models\TopRequest;
use App\Models\WalletTopup;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TransactionInvoiceController extends Controller
{
    public function show(Request $request, string $type, int $id)
    {
        [$normalizedType, $invoice] = $this->buildInvoice($type, $id);
        $view = $this->resolveTemplate($normalizedType);

        return response()->view($view, [
            'invoice' => $invoice,
        ]);
    }

    public function download(Request $request, string $type, int $id)
    {
        [$normalizedType, $invoice] = $this->buildInvoice($type, $id);
        $view = $this->resolveTemplate($normalizedType);

        $pdf = Pdf::loadView($view, [
            'invoice' => $invoice,
        ])->setPaper('a4');

        $safeReference = preg_replace('/[^A-Za-z0-9_\-]/', '-', (string) ($invoice['reference'] ?? $id));
        $fileName = 'invoice_' . $normalizedType . '_' . $safeReference . '.pdf';

        return $pdf->download($fileName);
    }

    private function resolveTemplate(string $type): string
    {
        return match ($type) {
            'wallet_topup' => 'invoices.wallet_topup',
            'account_topup' => 'invoices.account_topup',
            'exchange_request' => 'invoices.exchange_request',
            default => abort(422, 'Unsupported transaction type.'),
        };
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'wallet' => 'wallet_topup',
            'ad_account', 'ad_account_topup' => 'account_topup',
            default => $type,
        };
    }

    private function buildInvoice(string $type, int $id): array
    {
        $normalizedType = $this->normalizeType($type);

        return match ($normalizedType) {
            'wallet_topup' => [$normalizedType, $this->walletTopupInvoice($id)],
            'account_topup' => [$normalizedType, $this->accountTopupInvoice($id)],
            'exchange_request' => [$normalizedType, $this->exchangeRequestInvoice($id)],
            default => abort(422, 'Unsupported transaction type.'),
        };
    }

    private function walletTopupInvoice(int $id): array
    {
        $item = WalletTopup::with([
            'client:id,name,email',
            'clientProfileByUserId:id,user_id,clientName',
            'clientProfileByClientId:id,user_id,clientName',
            'approver:id,name,email',
        ])->findOrFail($id);

        $amount = (float) $item->amount;

        return [
            'invoice_number' => 'INV-WALLET-' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT),
            'transaction_type' => 'Wallet Topup',
            'reference' => $item->request_id ?? ('WALLET-' . $item->id),
            'status' => $item->status,
            'currency' => $item->currency,
            'amount' => $amount,
            'created_at' => optional($item->created_at)?->toDateTimeString(),
            'approved_at' => optional($item->approved_at)?->toDateTimeString(),
            'approved_by' => optional($item->approver)->name,
            'client' => [
                'id' => $item->client_id,
                'name' => $this->resolveClientName($item),
                'email' => optional($item->client)->email,
            ],
            'details' => [
                'payment_mode' => $item->payment_mode,
                'transaction_hash' => $item->transaction_hash,
            ],
            'line_items' => [
                [
                    'description' => 'Wallet topup credit',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ],
            ],
            'subtotal' => $amount,
            'total' => $amount,
        ];
    }

    private function accountTopupInvoice(int $id): array
    {
        $item = TopRequest::with([
            'client:id,name,email',
            'adAccountRequest:id,request_id,business_name,business_manager_id,account_management_id',
            'adAccountRequest.businessManager:id,name',
            'adAccountRequest.accountManagement:id,account_id,name,business_manager_id',
            'adAccountRequest.accountManagement.businessManager:id,name',
        ])->findOrFail($id);

        $amount = (float) $item->amount;
        $adAccount = $item->adAccountRequest;
        $account = optional($adAccount)->accountManagement;

        return [
            'invoice_number' => 'INV-ACCOUNT-' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT),
            'transaction_type' => 'Account Topup',
            'reference' => 'TOP-' . $item->id,
            'status' => $item->status,
            'currency' => $item->currency,
            'amount' => $amount,
            'created_at' => optional($item->created_at)?->toDateTimeString(),
            'approved_at' => optional($item->approved_at)?->toDateTimeString(),
            'approved_by' => null,
            'client' => [
                'id' => $item->client_id,
                'name' => optional($item->client)->name,
                'email' => optional($item->client)->email,
            ],
            'details' => [
                'ad_account_request_id' => optional($adAccount)->request_id,
                'business_name' => optional($adAccount)->business_name,
                'account_id' => optional($account)->account_id,
                'account_name' => optional($account)->name,
                'business_manager_name' => optional(optional($adAccount)->businessManager)->name
                    ?? optional(optional($account)->businessManager)->name,
            ],
            'line_items' => [
                [
                    'description' => 'Ad account topup',
                    'quantity' => 1,
                    'unit_price' => $amount,
                    'total' => $amount,
                ],
            ],
            'subtotal' => $amount,
            'total' => $amount,
        ];
    }

    private function exchangeRequestInvoice(int $id): array
    {
        $item = ExchangeRequest::with([
            'client:id,name,email',
            'clientProfileByUserId:id,user_id,clientName',
            'clientProfileByClientId:id,user_id,clientName',
            'approver:id,name,email',
        ])->findOrFail($id);

        $requestAmount = (float) $item->request_amount;
        $serviceFee = (float) $item->service_fee;
        $totalDeduction = (float) $item->total_deduction;
        $returnAmount = (float) $item->return_amount;

        return [
            'invoice_number' => 'INV-EXCHANGE-' . str_pad((string) $item->id, 6, '0', STR_PAD_LEFT),
            'transaction_type' => 'Exchange Request',
            'reference' => 'EXCH-' . $item->id,
            'status' => $item->status,
            'currency' => (string) ($item->base_currency ?? $item->based_cur),
            'amount' => $totalDeduction,
            'created_at' => optional($item->created_at)?->toDateTimeString(),
            'approved_at' => optional($item->approved_at)?->toDateTimeString(),
            'approved_by' => optional($item->approver)->name,
            'client' => [
                'id' => $item->client_id,
                'name' => $this->resolveClientName($item),
                'email' => optional($item->client)->email,
            ],
            'details' => [
                'base_currency' => (string) ($item->base_currency ?? $item->based_cur),
                'conversion_currency' => (string) ($item->converion_currency ?? $item->convertion_cur),
                'conversion_rate' => (string) $item->convertion_rate,
                'return_amount' => $returnAmount,
            ],
            'line_items' => [
                [
                    'description' => 'Exchange amount',
                    'quantity' => 1,
                    'unit_price' => $requestAmount,
                    'total' => $requestAmount,
                ],
                [
                    'description' => 'Service fee',
                    'quantity' => 1,
                    'unit_price' => $serviceFee,
                    'total' => $serviceFee,
                ],
            ],
            'subtotal' => $requestAmount,
            'total' => $totalDeduction,
        ];
    }

    private function resolveClientName(object $item): ?string
    {
        return data_get($item, 'clientProfileByUserId.clientName')
            ?? data_get($item, 'clientProfileByClientId.clientName')
            ?? data_get($item, 'client.client.clientName')
            ?? data_get($item, 'client.name');
    }
}
