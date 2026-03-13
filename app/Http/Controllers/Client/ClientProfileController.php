<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClientProfileController extends Controller
{
    public function fields(Request $request)
    {
        $allowedColumns = [
            'id',
            'clientCode',
            'clientName',
            'country',
            'email',
            'phone',
            'clientType',
            'niche',
            'marketCountry',
            'settlementMode',
            'statementCycle',
            'settlementCurrency',
            'cooperationStart',
            'serviceFeePercent',
            'serviceFeeEffectiveTime',
            'enabled',
            'admin_created_by',
            'primary_admin_user_id',
            'created_at',
            'updated_at',
        ];

        $requestedColumns = $request->input('columns', ['serviceFeePercent']);

        if (is_string($requestedColumns)) {
            $requestedColumns = array_filter(array_map('trim', explode(',', $requestedColumns)));
        }

        if (!is_array($requestedColumns) || empty($requestedColumns)) {
            throw ValidationException::withMessages([
                'columns' => ['The columns field must be a non-empty array or comma-separated string.'],
            ]);
        }

        $requestedColumns = array_values(array_unique($requestedColumns));
        $invalidColumns = array_values(array_diff($requestedColumns, $allowedColumns));

        if (!empty($invalidColumns)) {
            throw ValidationException::withMessages([
                'columns' => ['Invalid column(s): '.implode(', ', $invalidColumns)],
            ]);
        }

        $tenantClientId = (int) $request->attributes->get('current_client_id');

        $client = Client::query()
            ->where('id', $tenantClientId)
            ->first($requestedColumns);

        if (!$client) {
            return response()->json([
                'status' => 'error',
                'message' => 'Client profile not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $client->only($requestedColumns),
        ]);
    }
}
