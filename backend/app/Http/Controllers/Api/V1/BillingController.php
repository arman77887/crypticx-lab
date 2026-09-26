<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\BillingUnavailableException;
use App\Http\Controllers\Controller;
use App\Services\Billing\BillingManager;
use App\Services\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class BillingController extends Controller
{
    public function status(
        BillingManager $billing,
        PlatformSettingsService $settings,
    ): JsonResponse {
        if (! $settings->boolean('premium_enabled', false)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'configured' => false,
                    'checkout_enabled' => false,
                    'provider' => null,
                ],
            ]);
        }

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
        PlatformSettingsService $settings,
    ): JsonResponse {
        if (! $settings->boolean('premium_enabled', false)) {
            return response()->json([
                'success' => false,
                'code' => 'PREMIUM_DISABLED',
                'message' => 'Premium subscriptions are temporarily unavailable.',
            ], 503);
        }

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
