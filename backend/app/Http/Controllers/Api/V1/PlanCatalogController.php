<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;

class PlanCatalogController extends Controller
{
    public function index(
        EntitlementService $entitlements,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $entitlements->publicCatalog(),
        ]);
    }
}
