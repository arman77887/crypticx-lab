<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Billing\BillingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BillingController extends Controller
{
    public function status(
        BillingManager $billing,
    ): JsonResponse {
        try {
            $status = $billing->status();
        } catch (BillingUnavailableException) {
            $status = [
                'configured' => false,
                'checkout_enabled' => false,
                'provider' => null,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function checkout(
        Request $request,
        BillingManager $billing,
    ): JsonResponse {
        $validated = $request->validate([
            'plan_code' => [
                'required',
                'string',
                Rule::in([
                    'professional',
                    'team',
                ]),
            ],
        ]);

        try {
            $checkout = $billing->createCheckout(
                $request->user(),
                $validated['plan_code'],
            );
        } catch (BillingUnavailableException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'BILLING_NOT_CONFIGURED',
                'message' => $exception->getMessage(),
            ], 503);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'code' => 'INVALID_BILLING_PLAN',
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $checkout,
        ]);
    }
}
