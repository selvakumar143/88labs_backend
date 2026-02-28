<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalletTopup;
use App\Models\AdAccountTopupRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\AdAccountRequest;

class FinancialExportController extends Controller
{
    public function exportTopup(Request $request)
    {
        $request->validate([
            'type' => 'required|in:wallet,ad_account',
            'client_id' => 'nullable',
            'status' => 'nullable|in:pending,approved,rejected',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);
    
        if ($request->type === 'wallet') {
    
            // WALLET QUERY
            $model = WalletTopup::with('client');
    
            if ($request->client_id && $request->client_id !== 'all') {
                $model->where('client_id', $request->client_id);
            }
    
            if ($request->status && $request->status !== 'all') {
                $model->where('status', $request->status);
            }
    
        } else {
    
            // AD ACCOUNT QUERY
            $model = AdAccountRequest::with('client');
    
            if ($request->client_id && $request->client_id !== 'all') {
                $model->where('client_id', $request->client_id);
            }
        }
    
        // Date filter
        if ($request->start_date && $request->end_date) {
            $model->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay()
            ]);
        }
    
        $records = $model->orderBy('created_at', 'desc')->get();
    
        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No records found'
            ], 404);
        }
    
        /*
        |--------------------------------------------------------------------------
        | Build HTML
        |--------------------------------------------------------------------------
        */
    
        if ($request->type === 'wallet') {
    
            $html = '
            <h2 style="text-align:center;">Wallet Topup Report</h2>
            <table width="100%" border="1" cellspacing="0" cellpadding="8">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Request ID</th>
                        <th>Client</th>
                        <th>Business Name</th>
                        <th>Hash</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
            ';
    
            foreach ($records as $index => $record) {
    
                // Get business name using client_id
                $businessName = AdAccountRequest::where('client_id', $record->client_id)
                                    ->latest()
                                    ->value('business_name');
    
                $html .= '
                    <tr>
                        <td>' . ($index + 1) . '</td>
                        <td>' . $record->id . '</td>
                        <td>' . ($record->client->name ?? '-') . '</td>
                        <td>' . ($businessName ?? '-') . '</td>
                        <td>' . ($record->transaction_hash ?? '-') . '</td>
                        <td>USD ' . number_format($record->amount, 2) . '</td>
                        <td>' . strtoupper($record->status) . '</td>
                        <td>' . $record->created_at->format('d M Y, h:i A') . '</td>
                    </tr>
                ';
            }
    
        } else {
    
            $html = '
            <h2 style="text-align:center;">Ad Account Request Report</h2>
            <table width="100%" border="1" cellspacing="0" cellpadding="8">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Request ID</th>
                        <th>Client</th>
                        <th>Email</th>
                        <th>Amount</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
            ';
    
            foreach ($records as $index => $record) {
    
                $html .= '
                    <tr>
                        <td>' . ($index + 1) . '</td>
                        <td>' . $record->id . '</td>
                        <td>' . ($record->client->name ?? '-') . '</td>
                        <td>' . ($record->client->email ?? '-') . '</td>
                        <td>$' . number_format($record->amount, 2) . '</td>
                        <td>' . $record->currency . '</td>
                        <td>' . strtoupper($record->status ?? "approved") . '</td>
                        <td>' . $record->created_at->format('d M Y, h:i A') . '</td>
                    </tr>
                ';
            }
        }
    
        $html .= '</tbody></table>';
    
        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'landscape');
    
        $fileName = $request->type . '_report_' . now()->format('Ymd_His') . '.pdf';
    
        return $pdf->download($fileName);
    }
}