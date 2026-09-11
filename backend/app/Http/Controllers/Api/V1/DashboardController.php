<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AggregateRiskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        AggregateRiskService $aggregateRisk,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $aggregateRisk->user($request->user()),
        ]);
    }
}
