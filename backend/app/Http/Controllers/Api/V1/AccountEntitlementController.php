<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EntitlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountEntitlementController extends Controller
{
    public function show(
        Request $request,
        EntitlementService $entitlements,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Unauthenticated.',
        );

        return response()->json([
            'success' => true,
            'data' => $entitlements->forUser(
                $user,
            ),
        ]);
    }
}
