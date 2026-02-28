<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ForexRateService;
use Throwable;

class ForexRateController extends Controller
{
    public function latest(ForexRateService $forexRateService)
    {
        try {
            return response()->json([
                'status' => 'success',
                'data' => $forexRateService->getLatestRates(),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
