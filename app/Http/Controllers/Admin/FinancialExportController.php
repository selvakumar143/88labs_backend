<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WalletTopup;
use App\Models\AdAccountTopupRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class FinancialExportController extends Controller
{
    public function exportTopup(Request $request)
    {

        /*
        |--------------------------------------------------------------------------
        | Validate Request
        |--------------------------------------------------------------------------
        */
        $request->validate([
            'type' => 'required|in:wallet,ad_account',
            'client_id' => 'nullable',
            'status' => 'nullable|in:pending,approved,rejected',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date'
        ]);

        /*
        |--------------------------------------------------------------------------
        | Select Model Dynamically (Reusable Logic)
        |--------------------------------------------------------------------------
        */
        $model = $request->type === 'wallet'
            ? WalletTopup::query()
            : AdAccountTopupRequest::query();

        /*
        |--------------------------------------------------------------------------
        | Apply Filters
        |--------------------------------------------------------------------------
        */

        // Filter by client
        if ($request->client_id && $request->client_id !== 'all') {
            $model->where('client_id', $request->client_id);
        }

        // Filter by status
        if ($request->status && $request->status !== 'all') {
            $model->where('status', $request->status);
        }

        // Date Range Filter
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
        | Build HTML Dynamically
        |--------------------------------------------------------------------------
        */

        $html = '
        <h2 style="text-align:center;">Topup Report</h2>
        <table width="100%" border="1" cellspacing="0" cellpadding="8">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Client ID</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
        ';

        foreach ($records as $index => $record) {
            $html .= '
                <tr>
                    <td>' . ($index + 1) . '</td>
                    <td>' . $record->client_id . '</td>
                    <td>' . $record->amount . '</td>
                    <td>' . ucfirst($record->status) . '</td>
                    <td>' . $record->created_at->format('d M Y') . '</td>
                </tr>
            ';
        }

        $html .= '
            </tbody>
        </table>
        ';

        $pdf = Pdf::loadHTML($html)->setPaper('a4', 'portrait');

        $fileName = $request->type . '_topup_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($fileName);
    }
}