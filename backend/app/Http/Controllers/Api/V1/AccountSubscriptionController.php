<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\EntitlementService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountSubscriptionController extends Controller
{
    public function show(
        Request $request,
        SubscriptionService $subscriptions,
        EntitlementService $entitlements,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user, 401);

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' =>
                    $subscriptions->accountState($user),

                'effective_entitlements' =>
                    $entitlements->forUser($user),
            ],
        ]);
    }
}
