<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class FinancialExportController extends Controller
{
    public function exportTopup(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:all,wallet,wallet_topup,ad_account,ad_account_topup,account_topup,exchange_request',
            'client_id' => 'nullable',
            'status' => 'nullable|string',
            'search' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'format' => 'nullable|in:csv,pdf',
        ]);

        $typeMap = [
            'wallet' => 'wallet_topup',
            'wallet_topup' => 'wallet_topup',
            'ad_account' => 'account_topup',
            'ad_account_topup' => 'account_topup',
            'account_topup' => 'account_topup',
            'exchange_request' => 'exchange_request',
            'all' => 'all',
        ];

        $request->merge($validated);
        $request->merge(['type' => $typeMap[$validated['type']]]);

        return app(TransactionController::class)->export($request);
    }
}
